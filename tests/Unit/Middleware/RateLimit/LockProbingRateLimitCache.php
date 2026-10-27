<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware\RateLimit;

use Sabservis\Api\Middleware\RateLimit\LockingRateLimitCache;
use Sabservis\Api\Middleware\RateLimit\RateLimitCache;
use Symfony\Component\Lock\LockFactory;

/**
 * Test double that records whether the rate limit lock is held while incrementing.
 */
final class LockProbingRateLimitCache implements RateLimitCache
{

	public bool|null $lockWasHeld = null;

	public function __construct(private readonly LockFactory $lockFactory)
	{
	}

	public function increment(string $key, int $maxRequests, int $windowSeconds): int
	{
		// A second lock on the same resource must conflict while the decorator holds it
		$probe = $this->lockFactory->createLock(LockingRateLimitCache::LOCK_PREFIX . $key);
		$this->lockWasHeld = !$probe->acquire(false);

		if (!$this->lockWasHeld) {
			$probe->release();
		}

		return 1;
	}

}
