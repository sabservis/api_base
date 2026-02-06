<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware\RateLimit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Middleware\RateLimit\RateLimitCache;

final class RateLimitCacheTest extends TestCase
{

	#[Test]
	public function customImplementationReturnsIncrementedCount(): void
	{
		$cache = new class implements RateLimitCache
		{

			private array $storage = [];

			public function increment(string $key, int $maxRequests, int $windowSeconds): int
			{
				$this->storage[$key] = ($this->storage[$key] ?? 0) + 1;

				return $this->storage[$key];
			}

		};

		self::assertSame(1, $cache->increment('ip:1.2.3.4', 10, 60));
		self::assertSame(2, $cache->increment('ip:1.2.3.4', 10, 60));
	}

}
