<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function bin2hex;
use function random_bytes;
use function sprintf;

/**
 * Middleware that adds a unique request ID to each request.
 *
 * - Reads X-Request-ID from incoming request header (for distributed tracing)
 * - Generates a new UUID v4 if not present
 * - Adds request ID to request attributes (accessible via $request->getAttribute('requestId'))
 * - Adds X-Request-ID header to response
 *
 * Priority 5 ensures this runs very early, before logging and other middleware.
 */
#[MiddlewarePriority(5)]
final class RequestIdMiddleware implements Middleware
{

	public const HeaderName = 'X-Request-ID';

	public const AttributeName = 'requestId';

	/**
	 * Number of random bytes for UUID v4 generation (128-bit = 16 bytes).
	 */
	private const UUID_BYTE_LENGTH = 16;

	/**
	 * UUID v4 version byte masks and values (RFC 4122).
	 */
	private const UUID_VERSION_MASK = "\x0F";

	private const UUID_VERSION_4 = "\x40";

	private const UUID_VARIANT_MASK = "\x3F";

	private const UUID_VARIANT_RFC4122 = "\x80";

	/**
	 * Generate UUID v4 (random).
	 */
	private function generateUuid(): string
	{
		$bytes = random_bytes(self::UUID_BYTE_LENGTH);

		// Set version to 4 (random)
		$bytes[6] = $bytes[6] & self::UUID_VERSION_MASK | self::UUID_VERSION_4;
		// Set variant to RFC 4122
		$bytes[8] = $bytes[8] & self::UUID_VARIANT_MASK | self::UUID_VARIANT_RFC4122;

		return sprintf(
			'%s-%s-%s-%s-%s',
			bin2hex($bytes[0] . $bytes[1] . $bytes[2] . $bytes[3]),
			bin2hex($bytes[4] . $bytes[5]),
			bin2hex($bytes[6] . $bytes[7]),
			bin2hex($bytes[8] . $bytes[9]),
			bin2hex($bytes[10] . $bytes[11] . $bytes[12] . $bytes[13] . $bytes[14] . $bytes[15]),
		);
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		// Use existing request ID from header or generate new one
		$requestId = $request->getHeader(self::HeaderName) ?? $this->generateUuid();

		// Add to request attributes for use in application
		$request = $request->withAttribute(self::AttributeName, $requestId);

		// Execute next middleware
		$response = $next($request, $response);

		// Add request ID to response header
		return $response->withHeader(self::HeaderName, $requestId);
	}

}
