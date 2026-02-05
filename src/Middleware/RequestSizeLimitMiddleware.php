<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function round;
use function sprintf;
use function strlen;

/**
 * Middleware for limiting request body size to prevent DoS attacks.
 *
 * Features:
 * - Configurable maximum body size
 * - Checks Content-Length header first (fast rejection)
 * - Validates actual body size as fallback
 * - Returns 413 Payload Too Large on violation
 *
 * Usage:
 * ```neon
 * api:
 *     middlewares:
 *         - Sabservis\Api\Middleware\RequestSizeLimitMiddleware(maxBodySize: 10_485_760) # 10MB
 * ```
 */
#[MiddlewarePriority(15)]
final class RequestSizeLimitMiddleware implements Middleware
{

	private const BYTES_PER_KB = 1_024;

	private const BYTES_PER_MB = 1_024 * 1_024;

	private const DEFAULT_MAX_BODY_SIZE = 10 * self::BYTES_PER_MB; // 10 MB

	public function __construct(
		private readonly int $maxBodySize = self::DEFAULT_MAX_BODY_SIZE,
	)
	{
	}

	private function formatBytes(int $bytes): string
	{
		if ($bytes >= self::BYTES_PER_MB) {
			return round($bytes / self::BYTES_PER_MB, 1) . ' MB';
		}

		if ($bytes >= self::BYTES_PER_KB) {
			return round($bytes / self::BYTES_PER_KB, 1) . ' KB';
		}

		return $bytes . ' B';
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		// Fast path: check Content-Length header first
		$contentLength = $request->getHeader('Content-Length');

		if ($contentLength !== null) {
			$declaredSize = (int) $contentLength;

			if ($declaredSize > $this->maxBodySize) {
				throw new ClientErrorException(
					sprintf(ErrorMessages::PAYLOAD_TOO_LARGE, $this->formatBytes($this->maxBodySize)),
					413,
				);
			}
		}

		// Fallback: check actual body size
		// This catches cases where Content-Length is missing or incorrect
		$actualSize = strlen($request->getContents());

		if ($actualSize > $this->maxBodySize) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::PAYLOAD_TOO_LARGE, $this->formatBytes($this->maxBodySize)),
				413,
			);
		}

		return $next($request, $response);
	}

}
