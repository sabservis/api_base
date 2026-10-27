<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware\RateLimit;

use Symfony\Component\Lock\LockFactory;

/**
 * Serializes increments per key using symfony/lock.
 *
 * Cache adapters implement increment as read-modify-write, so concurrent requests
 * can read the same count and the limit is exceeded. Use a shared lock store
 * (Redis, database, flock on single host) for multi-process deployments.
 */
final class LockingRateLimitCache implements RateLimitCache
{

	public const LOCK_PREFIX = 'sabservis_api_ratelimit_';

	/**
	 * Lock TTL in seconds - auto-expires if the process dies while holding it.
	 */
	private const LOCK_TTL_SECONDS = 5.0;

	public function __construct(
		private readonly RateLimitCache $inner,
		private readonly LockFactory $lockFactory,
	)
	{
	}

	public function increment(string $key, int $maxRequests, int $windowSeconds): int
	{
		$lock = $this->lockFactory->createLock(self::LOCK_PREFIX . $key, self::LOCK_TTL_SECONDS);
		$lock->acquire(true);

		try {
			return $this->inner->increment($key, $maxRequests, $windowSeconds);
		} finally {
			$lock->release();
		}
	}

}
