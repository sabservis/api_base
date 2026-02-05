<?php declare(strict_types = 1);

namespace Sabservis\Api\Application;

use RuntimeException;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\Middleware;
use Sabservis\Api\Utils\ByteFormatter;
use Sabservis\Api\Utils\HeaderSanitizer;
use Throwable;
use function call_user_func;
use function fclose;
use function feof;
use function flush;
use function fread;
use function header;
use function in_array;
use function is_resource;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function ucwords;

/**
 * Main API application - handles the full request lifecycle.
 */
final class ApiApplication
{

	/**
	 * Chunk size for streaming response body (8 KB).
	 */
	private const STREAM_CHUNK_SIZE = 8_192;

	private const UniqueHeaders = ['content-type'];

	private bool $catchExceptions = false;

	/** @var callable|Middleware */
	private $chain;

	/** @var array<string> */
	private array $trustedProxies = [];

	private int|null $maxRequestBodySize = null;

	public function __construct(
		callable|Middleware $chain,
		private ErrorHandler|null $errorHandler = null,
	)
	{
		$this->chain = $chain;
	}

	public function setCatchExceptions(bool $catch = true): void
	{
		$this->catchExceptions = $catch;
	}

	/**
	 * Set trusted proxy IP addresses/ranges.
	 *
	 * When set, X-Forwarded-Proto header will only be trusted if the request
	 * comes from one of these IPs. This prevents HTTPS spoofing attacks.
	 *
	 * @param array<string> $proxies List of trusted proxy IPs or CIDR ranges (e.g., ['10.0.0.0/8', '192.168.1.1'])
	 */
	public function setTrustedProxies(array $proxies): void
	{
		$this->trustedProxies = $proxies;
	}

	/**
	 * Set maximum allowed request body size.
	 *
	 * SECURITY: When set, body size is checked BEFORE reading into memory,
	 * preventing memory exhaustion (DoS) attacks from large payloads.
	 *
	 * @param int $bytes Maximum body size in bytes (e.g., 10 * 1_024 * 1_024 for 10MB)
	 */
	public function setMaxRequestBodySize(int $bytes): void
	{
		$this->maxRequestBodySize = $bytes;
	}

	public function run(): ApiResponse
	{
		// SECURITY: Use limited body reading when max size is configured
		if ($this->maxRequestBodySize !== null) {
			try {
				$request = ApiRequest::fromGlobalsWithLimit($this->maxRequestBodySize);
			} catch (ClientErrorException $e) {
				return $this->handleEarlyException($e);
			}

			return $this->runWith($request);
		}

		return $this->runWith(ApiRequest::fromGlobals());
	}

	public function runWith(ApiRequest $request): ApiResponse
	{
		// SECURITY: Check body size limit for requests created outside run()
		if ($this->maxRequestBodySize !== null) {
			$bodySize = strlen($request->getContents());

			if ($bodySize > $this->maxRequestBodySize) {
				$exception = new ClientErrorException(
					sprintf(ErrorMessages::PAYLOAD_TOO_LARGE, ByteFormatter::format($this->maxRequestBodySize)),
					413,
				);

				return $this->handleEarlyException($exception, $request);
			}
		}

		// Apply trusted proxies configuration to request
		if ($this->trustedProxies !== []) {
			$request = $request->withTrustedProxies($this->trustedProxies);
		}

		$response = new ApiResponse();

		try {
			$response = call_user_func(
				$this->chain,
				$request,
				$response,
				static fn (ApiRequest $request, ApiResponse $response): ApiResponse => $response,
			);

			if (!isset($response)) {
				throw new RuntimeException('Final response cannot be NULL or unset');
			}
		} catch (Throwable $e) {
			if ($this->catchExceptions !== true) {
				throw $e;
			}

			// Use error handler if available
			if ($this->errorHandler !== null) {
				try {
					$response = $this->errorHandler->handle($e, $request);
				} catch (Throwable $fatal) {
					// Error handler itself failed, use fatal fallback
					$response = $this->errorHandler->handleFatal($fatal, $request);
				}
			}
		}

		return $this->finalize($response);
	}

	/**
	 * Handle exceptions that occur before the middleware chain.
	 *
	 * These exceptions (like payload too large) happen before we have a proper
	 * request context, so we handle them separately but still use the ErrorHandler
	 * for consistent JSON error responses.
	 */
	private function handleEarlyException(Throwable $e, ApiRequest|null $request = null): ApiResponse
	{
		if ($this->catchExceptions !== true) {
			throw $e;
		}

		$request ??= new ApiRequest('GET', '/');

		if ($this->errorHandler !== null) {
			try {
				$response = $this->errorHandler->handle($e, $request);
			} catch (Throwable $fatal) {
				$response = $this->errorHandler->handleFatal($fatal, $request);
			}
		} else {
			// No error handler - create minimal JSON response
			$response = (new ApiResponse())
				->withStatus($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500)
				->writeJsonBody([
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
				]);
		}

		return $this->finalize($response);
	}

	private function finalize(ApiResponse $response): ApiResponse
	{
		$this->sendStatus($response);
		$this->sendHeaders($response);
		$this->sendBody($response);

		return $response;
	}

	private function sendStatus(ApiResponse $response): void
	{
		$version = $response->getProtocolVersion();
		$status = $response->getStatusCode();
		$phrase = $response->getReasonPhrase();
		header(sprintf('HTTP/%s %s %s', $version, $status, $phrase));
	}

	private function sendHeaders(ApiResponse $response): void
	{
		foreach ($response->getHeaders() as $name => $values) {
			$this->sendHeaderValues($name, $values);
		}
	}

	/**
	 * Send all values for a single header.
	 *
	 * For headers in UniqueHeaders list, only the last value is sent (replace=true).
	 * For other headers (like Set-Cookie), each value is sent as a separate header line.
	 *
	 * @param list<string> $values
	 */
	private function sendHeaderValues(string $name, array $values): void
	{
		// Sanitize header name
		$normalizedName = str_replace('-', ' ', $name);
		$normalizedName = ucwords($normalizedName);
		$normalizedName = str_replace(' ', '-', $normalizedName);

		$isUnique = in_array(strtolower($name), self::UniqueHeaders, true);

		foreach ($values as $index => $value) {
			// SECURITY: Remove CRLF characters to prevent HTTP response splitting/header injection
			$value = HeaderSanitizer::sanitize($value);

			// For unique headers, replace previous value; for multi-value headers, append
			// First value always replaces any existing header, subsequent values append
			$replace = $isUnique || $index === 0;

			header(sprintf('%s: %s', $normalizedName, $value), $replace);
		}
	}

	private function sendBody(ApiResponse $response): void
	{
		$body = $response->getBody();

		if (is_resource($body)) {
			while (!feof($body)) {
				echo fread($body, self::STREAM_CHUNK_SIZE);
				flush();
			}

			fclose($body);
		} else {
			echo $body;
		}
	}

}
