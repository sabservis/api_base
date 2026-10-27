<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware\RateLimit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Middleware\RateLimit\LockingRateLimitCache;
use Sabservis\Api\Middleware\RateLimit\RateLimitCache;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class LockingRateLimitCacheTest extends TestCase
{

	#[Test]
	public function holdsLockDuringIncrement(): void
	{
		$lockFactory = new LockFactory(new InMemoryStore());
		$inner = new LockProbingRateLimitCache($lockFactory);

		$result = (new LockingRateLimitCache($inner, $lockFactory))->increment('client', 10, 60);

		self::assertSame(1, $result);
		self::assertTrue($inner->lockWasHeld);
	}

	#[Test]
	public function releasesLockWhenLimitExceeded(): void
	{
		$lockFactory = new LockFactory(new InMemoryStore());
		$inner = new ThrowingRateLimitCache();

		try {
			(new LockingRateLimitCache($inner, $lockFactory))->increment('client', 1, 60);
			self::fail('Expected 429');
		} catch (ClientErrorException $e) {
			self::assertSame(429, $e->getCode());
		}

		$probe = $lockFactory->createLock(LockingRateLimitCache::LOCK_PREFIX . 'client');
		self::assertTrue($probe->acquire(false), 'Lock must be released after exception');
	}

}

final class ThrowingRateLimitCache implements RateLimitCache
{

	public function increment(string $key, int $maxRequests, int $windowSeconds): int
	{
		throw new ClientErrorException('Too many requests', 429);
	}

}
