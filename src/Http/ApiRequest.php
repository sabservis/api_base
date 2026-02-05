<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use ReflectionClass;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Utils\ByteFormatter;
use Sabservis\Api\Utils\ClientIpResolver;
use Sabservis\Api\Utils\IpMatcher;
use Sabservis\Api\Utils\JsonLimits;
use function array_change_key_case;
use function array_key_exists;
use function array_keys;
use function count;
use function ctype_digit;
use function fclose;
use function feof;
use function file_get_contents;
use function fopen;
use function fread;
use function is_array;
use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function parse_url;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use const CASE_LOWER;
use const JSON_ERROR_NONE;
use const PHP_URL_PATH;
use const UPLOAD_ERR_NO_FILE;

class ApiRequest
{

	private const STREAM_CHUNK_SIZE = 8_192;

	/** @var array<string, mixed> */
	private array $attributes = [];

	/** @var array<string, string> */
	private array $headers = [];

	/** @var array<string, UploadedFile|array<UploadedFile>> */
	private array $uploadedFiles = [];

	/** @var array<string> */
	private array $trustedProxies = [];

	/**
	 * @param array<string, string> $headers
	 * @param array<string, mixed> $queryParams
	 * @param array<string, mixed> $cookies
	 * @param array<string, mixed> $serverParams
	 * @param array<string, mixed> $files Raw $_FILES array
	 */
	public function __construct(
		private string $method,
		private string $uri,
		array $headers = [],
		private string $body = '',
		private array $queryParams = [],
		private mixed $parsedBody = null,
		private array $cookies = [],
		private array $serverParams = [],
		array $files = [],
	)
	{
		// Normalize header names to lowercase for case-insensitive lookup
		$this->headers = array_change_key_case($headers, CASE_LOWER);
		$this->uploadedFiles = $this->normalizeFiles($files);
	}

	// === Factory ===

	public static function fromGlobals(): self
	{
		return new self(
			method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
			uri: $_SERVER['REQUEST_URI'] ?? '/',
			headers: self::parseHeaders(),
			body: file_get_contents('php://input') ?: '',
			queryParams: $_GET,
			parsedBody: $_POST ?: null,
			cookies: $_COOKIE,
			serverParams: $_SERVER,
			files: $_FILES,
		);
	}

	/**
	 * Create request from global state with body size limit.
	 *
	 * SECURITY: This method checks Content-Length header BEFORE reading body,
	 * preventing memory exhaustion attacks. For chunked transfers (no Content-Length),
	 * body is read with a hard limit.
	 *
	 * @param int $maxBodySize Maximum allowed body size in bytes
	 * @throws ClientErrorException When body exceeds limit (413 Payload Too Large)
	 * @throws ClientErrorException When Content-Length is invalid (400 Bad Request)
	 */
	public static function fromGlobalsWithLimit(int $maxBodySize): self
	{
		// SECURITY: Check Content-Length BEFORE reading body to prevent memory DoS
		$contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;

		if ($contentLength !== null) {
			// SECURITY: Validate Content-Length is a non-negative integer
			// Negative values could bypass size checks, non-numeric could cause issues
			if (!ctype_digit($contentLength)) {
				throw new ClientErrorException(ErrorMessages::INVALID_CONTENT_LENGTH, 400);
			}

			if ((int) $contentLength > $maxBodySize) {
				throw new ClientErrorException(
					sprintf(ErrorMessages::PAYLOAD_TOO_LARGE, ByteFormatter::format($maxBodySize)),
					413,
				);
			}
		}

		// Read body with limit (handles chunked transfer or missing Content-Length)
		$stream = fopen('php://input', 'rb');

		if ($stream === false) {
			$body = '';
		} else {
			try {
				$body = self::readBodyWithLimit($stream, $maxBodySize);
			} finally {
				fclose($stream);
			}
		}

		return new self(
			method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
			uri: $_SERVER['REQUEST_URI'] ?? '/',
			headers: self::parseHeaders(),
			body: $body,
			queryParams: $_GET,
			parsedBody: $_POST ?: null,
			cookies: $_COOKIE,
			serverParams: $_SERVER,
			files: $_FILES,
		);
	}

	/**
	 * Read from stream with a hard size limit.
	 *
	 * SECURITY: Reads in chunks and stops immediately when limit is exceeded,
	 * preventing memory exhaustion from large payloads.
	 *
	 * @param resource $stream Stream to read from
	 * @param int $maxSize Maximum bytes to read
	 * @throws ClientErrorException When stream exceeds limit (413 Payload Too Large)
	 */
	public static function readBodyWithLimit($stream, int $maxSize): string
	{
		$body = '';
		$bytesRead = 0;

		while (!feof($stream)) {
			$chunk = fread($stream, self::STREAM_CHUNK_SIZE);

			if ($chunk === false) {
				break;
			}

			$bytesRead += strlen($chunk);

			if ($bytesRead > $maxSize) {
				throw new ClientErrorException(
					sprintf(ErrorMessages::PAYLOAD_TOO_LARGE, ByteFormatter::format($maxSize)),
					413,
				);
			}

			$body .= $chunk;
		}

		return $body;
	}

	/**
	 * @return array<string, string>
	 */
	private static function parseHeaders(): array
	{
		$headers = [];

		foreach ($_SERVER as $key => $value) {
			if (str_starts_with($key, 'HTTP_')) {
				$name = str_replace('_', '-', substr($key, 5));
				$headers[strtolower($name)] = $value;
			}
		}

		if (isset($_SERVER['CONTENT_TYPE'])) {
			$headers['content-type'] = $_SERVER['CONTENT_TYPE'];
		}

		if (isset($_SERVER['CONTENT_LENGTH'])) {
			$headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
		}

		return $headers;
	}

	// === HTTP metody ===

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getUri(): RequestUri
	{
		return new RequestUri($this->uri);
	}

	public function getRawUri(): string
	{
		return $this->uri;
	}

	public function getPath(): string
	{
		$path = parse_url($this->uri, PHP_URL_PATH);

		return is_string($path) ? $path : '/';
	}

	public function getScheme(): string
	{
		return $this->isSecured() ? 'https' : 'http';
	}

	/**
	 * Check if request was made over HTTPS.
	 *
	 * Supports detection behind reverse proxy via X-Forwarded-Proto header,
	 * but ONLY when the request comes from a trusted proxy IP.
	 *
	 * Security: Without trusted proxies configured, X-Forwarded-Proto is ignored
	 * to prevent attackers from spoofing HTTPS.
	 */
	public function isSecured(): bool
	{
		// Direct HTTPS connection - always trusted
		if (($this->serverParams['HTTPS'] ?? '') !== '') {
			return true;
		}

		// Behind reverse proxy - check X-Forwarded-Proto header
		// Only trust if request comes from a trusted proxy
		if ($this->trustedProxies !== [] && $this->isFromTrustedProxy()) {
			$forwardedProto = $this->getHeader('X-Forwarded-Proto');

			return $forwardedProto === 'https';
		}

		return false;
	}

	/**
	 * Check if the direct connection is from a trusted proxy.
	 */
	private function isFromTrustedProxy(): bool
	{
		$remoteAddr = $this->serverParams['REMOTE_ADDR'] ?? '';

		if ($remoteAddr === '') {
			return false;
		}

		return IpMatcher::matchesAny($remoteAddr, $this->trustedProxies);
	}

	/**
	 * Get the client's IP address, respecting trusted proxy configuration.
	 *
	 * When behind a trusted proxy (nginx, Cloudflare, etc.), this method
	 * parses the X-Forwarded-For header to get the actual client IP.
	 *
	 * SECURITY: X-Forwarded-For is only parsed when the direct connection
	 * is from a configured trusted proxy. This prevents IP spoofing attacks.
	 *
	 * X-Forwarded-For format: "client, proxy1, proxy2, ..."
	 * - When behind trusted proxy, returns the rightmost non-trusted IP (original client)
	 * - When all IPs are trusted, returns the leftmost IP
	 *
	 * @return string Client IP address or "unknown" if not available
	 */
	public function getClientIp(): string
	{
		$remoteAddr = (string) ($this->serverParams['REMOTE_ADDR'] ?? 'unknown');

		// If no trusted proxies configured, always use REMOTE_ADDR
		if ($this->trustedProxies === []) {
			return $remoteAddr;
		}

		// Check if direct connection is from a trusted proxy
		if (!$this->isFromTrustedProxy()) {
			return $remoteAddr;
		}

		// Parse X-Forwarded-For using ClientIpResolver utility
		$forwardedFor = $this->getHeaderLine('x-forwarded-for');
		$clientIp = ClientIpResolver::extractFromForwardedFor($forwardedFor, $this->trustedProxies);

		// Validate the extracted IP
		if ($clientIp !== null && IpMatcher::isValid($clientIp)) {
			return $clientIp;
		}

		// Fallback to REMOTE_ADDR if extraction failed or header was empty
		return $remoteAddr;
	}

	/**
	 * Get configured trusted proxy IP addresses/ranges.
	 *
	 * @return array<string>
	 */
	public function getTrustedProxies(): array
	{
		return $this->trustedProxies;
	}

	/**
	 * Return a new instance with trusted proxy IPs configured.
	 *
	 * When trusted proxies are set, X-Forwarded-Proto header will only be
	 * trusted if the request comes from one of these IPs.
	 *
	 * @param array<string> $proxies List of trusted proxy IPs or CIDR ranges (e.g., ['10.0.0.0/8', '192.168.1.1'])
	 */
	public function withTrustedProxies(array $proxies): self
	{
		$new = clone $this;
		$new->trustedProxies = $proxies;

		return $new;
	}

	public function withMethod(string $method): self
	{
		$new = clone $this;
		$new->method = $method;

		return $new;
	}

	// === Headers ===

	public function hasHeader(string $name): bool
	{
		return isset($this->headers[strtolower($name)]);
	}

	public function getHeader(string $name): string|null
	{
		return $this->headers[strtolower($name)] ?? null;
	}

	/**
	 * Returns header value as string (empty string if not present).
	 * PSR-7 compatible method.
	 */
	public function getHeaderLine(string $name): string
	{
		return $this->headers[strtolower($name)] ?? '';
	}

	/**
	 * @return array<string, string>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	public function withHeader(string $name, string $value): self
	{
		$new = clone $this;
		$new->headers[strtolower($name)] = $value;

		return $new;
	}

	// === Body ===

	public function getContents(): string
	{
		return $this->body;
	}

	public function getParsedBody(): mixed
	{
		return $this->parsedBody;
	}

	public function withParsedBody(mixed $data): self
	{
		$new = clone $this;
		$new->parsedBody = $data;

		return $new;
	}

	// === Query a Cookies ===

	/**
	 * @return array<string, mixed>
	 */
	public function getQueryParams(): array
	{
		return $this->queryParams;
	}

	public function getQueryParam(string $name, mixed $default = null): mixed
	{
		return $this->queryParams[$name] ?? $default;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getCookies(): array
	{
		return $this->cookies;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getServerParams(): array
	{
		return $this->serverParams;
	}

	public function getServerParam(string $name, mixed $default = null): mixed
	{
		return $this->serverParams[$name] ?? $default;
	}

	public function getCookie(string $name, mixed $default = null): mixed
	{
		return $this->cookies[$name] ?? $default;
	}

	/**
	 * @param array<string, mixed> $cookies
	 */
	public function withCookies(array $cookies): self
	{
		$new = clone $this;
		$new->cookies = $cookies;

		return $new;
	}

	// === Uploaded Files ===

	/**
	 * Check if file was uploaded with given name.
	 */
	public function hasUploadedFile(string $name): bool
	{
		return isset($this->uploadedFiles[$name]);
	}

	/**
	 * Get single uploaded file by name.
	 * Returns null if not found or if multiple files were uploaded.
	 */
	public function getUploadedFile(string $name): UploadedFile|null
	{
		$file = $this->uploadedFiles[$name] ?? null;

		return $file instanceof UploadedFile ? $file : null;
	}

	/**
	 * Get all uploaded files for given name (for multiple file inputs).
	 * Returns array even for single file upload.
	 *
	 * @return array<UploadedFile>
	 */
	public function getUploadedFiles(string $name): array
	{
		$files = $this->uploadedFiles[$name] ?? [];

		if ($files instanceof UploadedFile) {
			return [$files];
		}

		return $files;
	}

	/**
	 * Get all uploaded files grouped by input name.
	 *
	 * @return array<string, UploadedFile|array<UploadedFile>>
	 */
	public function getAllUploadedFiles(): array
	{
		return $this->uploadedFiles;
	}

	/**
	 * Get names of all file inputs that have uploads.
	 *
	 * @return array<string>
	 */
	public function getUploadedFileNames(): array
	{
		return array_keys($this->uploadedFiles);
	}

	/**
	 * Return new instance with uploaded files.
	 *
	 * @param array<string, UploadedFile|array<UploadedFile>> $uploadedFiles
	 */
	public function withUploadedFiles(array $uploadedFiles): self
	{
		$new = clone $this;
		$new->uploadedFiles = $uploadedFiles;

		return $new;
	}

	/**
	 * Normalize $_FILES array to UploadedFile instances.
	 * Handles both single and multiple file uploads.
	 *
	 * @param array<string, mixed> $files
	 * @return array<string, UploadedFile|array<UploadedFile>>
	 */
	private function normalizeFiles(array $files): array
	{
		$normalized = [];

		foreach ($files as $name => $file) {
			if (!is_array($file) || !isset($file['error'])) {
				continue;
			}

			// Multiple files: name[], tmp_name[], etc. are arrays
			if (is_array($file['error'])) {
				// phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.NoAssignment
				/** @var array{name: array<string>, type: array<string>, size: array<int>, tmp_name: array<string>, error: array<int>} $file */
				$normalized[$name] = $this->normalizeMultipleFiles($file);
			} elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
				// phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.NoAssignment
				/** @var array{name: string, type: string, size: int, tmp_name: string, error: int} $file */
				$normalized[$name] = UploadedFile::fromArray($file);
			}
		}

		return $normalized;
	}

	/**
	 * Normalize multiple file upload structure.
	 *
	 * @param array{name: array<string>, type: array<string>, size: array<int>, tmp_name: array<string>, error: array<int>} $file
	 * @return array<UploadedFile>
	 */
	private function normalizeMultipleFiles(array $file): array
	{
		$files = [];
		$count = count($file['error']);

		for ($i = 0; $i < $count; $i++) {
			if ($file['error'][$i] === UPLOAD_ERR_NO_FILE) {
				continue;
			}

			$files[] = new UploadedFile(
				name: $file['name'][$i],
				type: $file['type'][$i],
				size: $file['size'][$i],
				tmpName: $file['tmp_name'][$i],
				error: $file['error'][$i],
			);
		}

		return $files;
	}

	// === Attributes ===

	public function hasAttribute(string $name): bool
	{
		return array_key_exists($name, $this->attributes);
	}

	public function getAttribute(string $name, mixed $default = null): mixed
	{
		return $this->attributes[$name] ?? $default;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	public function withAttribute(string $name, mixed $value): self
	{
		$new = clone $this;
		$new->attributes[$name] = $value;

		return $new;
	}

	// === API helpers ===

	public function hasParameter(string $name): bool
	{
		return array_key_exists($name, $this->getAttribute(RequestAttributes::Parameters->value, []));
	}

	public function getParameter(string $name, mixed $default = null): mixed
	{
		return $this->getAttribute(RequestAttributes::Parameters->value, [])[$name] ?? $default;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getParameters(): array
	{
		return $this->getAttribute(RequestAttributes::Parameters->value, []);
	}

	/**
	 * Check if request has a deserialized entity.
	 */
	public function hasEntity(): bool
	{
		return $this->getAttribute(RequestAttributes::RequestEntity->value) !== null;
	}

	public function getEntity(mixed $default = null): mixed
	{
		return $this->getAttribute(RequestAttributes::RequestEntity->value) ?? $default;
	}

	/**
	 * Returns the request entity with type checking.
	 *
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 * @throws RuntimeStateException When entity is not set or type doesn't match
	 */
	public function getTypedEntity(string $class): object
	{
		$entity = $this->getAttribute(RequestAttributes::RequestEntity->value);

		if ($entity === null) {
			throw new RuntimeStateException('No request entity found');
		}

		if (!$entity instanceof $class) {
			throw new RuntimeStateException(sprintf(
				'Expected entity of type %s, got %s',
				(new ReflectionClass($class))->getShortName(),
				$entity::class,
			));
		}

		return $entity;
	}

	/**
	 * Returns JSON decoded body.
	 *
	 * Uses JsonLimits::DEFAULT_DEPTH to prevent stack overflow attacks with deeply nested JSON.
	 *
	 * @return array<mixed>|null
	 */
	public function getJsonBody(): array|null
	{
		$contents = $this->getContents();

		if ($contents === '') {
			return null;
		}

		$decoded = json_decode($contents, true, JsonLimits::DEFAULT_DEPTH);

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * Returns JSON decoded body or throws on error.
	 *
	 * Unlike getJsonBody() which returns null for invalid JSON, this method
	 * throws a descriptive error that can be returned to the API consumer.
	 *
	 * @return array<mixed>
	 * @throws ClientErrorException When body is empty, JSON is invalid, or not an array/object
	 */
	public function getJsonBodyOrFail(): array
	{
		$contents = $this->getContents();

		if ($contents === '') {
			throw new ClientErrorException(ErrorMessages::JSON_EMPTY_BODY, 400);
		}

		$decoded = json_decode($contents, true, JsonLimits::DEFAULT_DEPTH);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::JSON_INVALID, json_last_error_msg()),
				400,
			);
		}

		if (!is_array($decoded)) {
			throw new ClientErrorException(ErrorMessages::JSON_NOT_ARRAY, 400);
		}

		return $decoded;
	}

	public function getEndpoint(): Endpoint|null
	{
		$endpoint = $this->getAttribute(RequestAttributes::Endpoint->value);

		return $endpoint instanceof Endpoint ? $endpoint : null;
	}

	/**
	 * Returns raw route parameters captured from URL path.
	 * These are the values extracted from path placeholders like /users/{id}.
	 *
	 * @return array<string, string>
	 */
	public function getRouteParams(): array
	{
		return $this->getAttribute(RequestAttributes::Router->value, []);
	}

}
