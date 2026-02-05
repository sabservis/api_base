<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware\RateLimit;

use Sabservis\Api\Exception\Api\ClientErrorException;

/**
 * Interface for rate limit cache operations.
 *
 * Provides atomic increment operation for rate limiting.
 */
interface RateLimitCache
{

	/**
	 * Increment counter and return new count.
	 *
	 * @param string $key Cache key for the rate limit counter
	 * @param int $maxRequests Maximum allowed requests before throwing
	 * @param int $windowSeconds Time window in seconds (for cache expiry)
	 * @return int New count after increment
	 * @throws ClientErrorException When limit exceeded (429 Too Many Requests)
	 */
	public function increment(string $key, int $maxRequests, int $windowSeconds): int;

}
