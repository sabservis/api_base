<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Psr\Log\LoggerInterface;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Schema\Endpoint;
use Throwable;
use function assert;
use function bin2hex;
use function is_object;
use function microtime;
use function random_bytes;
use function round;

/**
 * Audit logging middleware for fintech compliance.
 *
 * Logs all API requests with:
 * - Unique request ID (returned in X-Request-ID header)
 * - Request method, path, endpoint
 * - Response status code
 * - Duration in milliseconds
 * - User ID (if authenticated)
 * - Client IP address
 * - User agent
 *
 * Error requests are logged at error level with exception details.
 */
#[MiddlewarePriority(500)]
final class AuditLogMiddleware implements Middleware
{

	/**
	 * Number of random bytes for request ID generation (128-bit = 16 bytes).
	 */
	private const REQUEST_ID_BYTE_LENGTH = 16;

	public function __construct(private readonly LoggerInterface $logger)
	{
	}

	private function generateRequestId(): string
	{
		return bin2hex(random_bytes(self::REQUEST_ID_BYTE_LENGTH));
	}

	private function logRequest(
		ApiRequest $request,
		ApiResponse $response,
		float $startTime,
		string $requestId,
	): void
	{
		$duration = (microtime(true) - $startTime) * 1_000;
		$user = $request->getAttribute('user');

		$endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
		assert($endpoint instanceof Endpoint || $endpoint === null);

		$this->logger->info('API Request', [
			'request_id' => $requestId,
			'method' => $request->getMethod(),
			'path' => $request->getPath(),
			'endpoint' => $endpoint?->getTag(Endpoint::TAG_ID),
			'status' => $response->getStatusCode(),
			'duration_ms' => round($duration, 2),
			'user_id' => is_object($user) ? ($user->id ?? null) : null,
			'ip' => $request->getClientIp(),
			'user_agent' => $request->getHeader('User-Agent'),
		]);
	}

	private function logError(
		ApiRequest $request,
		Throwable $error,
		float $startTime,
		string $requestId,
	): void
	{
		$duration = (microtime(true) - $startTime) * 1_000;
		$user = $request->getAttribute('user');

		$this->logger->error('API Error', [
			'request_id' => $requestId,
			'method' => $request->getMethod(),
			'path' => $request->getPath(),
			'duration_ms' => round($duration, 2),
			'user_id' => is_object($user) ? ($user->id ?? null) : null,
			'ip' => $request->getClientIp(),
			'error_class' => $error::class,
			'error_message' => $error->getMessage(),
			'error_code' => $error->getCode(),
		]);
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		// Use request ID from RequestIdMiddleware if available, otherwise generate
		$requestId = $request->getAttribute(RequestIdMiddleware::AttributeName)
			?? $this->generateRequestId();
		$startTime = microtime(true);

		// Set legacy attribute name for backwards compatibility
		$request = $request->withAttribute('request_id', $requestId);

		try {
			$response = $next($request, $response);

			// Only add header if RequestIdMiddleware didn't already
			if (!$response->hasHeader('X-Request-ID')) {
				$response = $response->withHeader('X-Request-ID', $requestId);
			}

			$this->logRequest($request, $response, $startTime, $requestId);

			return $response;
		} catch (Throwable $e) {
			$this->logError($request, $e, $startTime, $requestId);

			throw $e;
		}
	}

}
