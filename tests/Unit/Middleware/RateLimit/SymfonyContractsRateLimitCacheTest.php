<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware\RateLimit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Middleware\RateLimit\SymfonyContractsRateLimitCache;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SymfonyContractsRateLimitCacheTest extends TestCase
{

	#[Test]
	public function incrementFirstRequest(): void
	{
		$cache = $this->createMock(CacheInterface::class);

		$callCount = 0;
		$cache->method('get')
			->willReturnCallback(function (string $key, callable $callback) use (&$callCount) {
				$callCount++;
				$item = $this->createMock(ItemInterface::class);
				$item->expects($this->once())->method('expiresAfter');

				if ($callCount === 1) {
					// First call - return initial count
					return $callback($item);
				}

				// Second call - return incremented count
				return $callback($item);
			});

		$cache->expects($this->once())->method('delete');

		$cacheAdapter = new SymfonyContractsRateLimitCache($cache);
		$count = $cacheAdapter->increment('test_key', 100, 60);

		self::assertSame(1, $count);
	}

	#[Test]
	public function incrementThrowsWhenLimitExceeded(): void
	{
		$cache = $this->createMock(CacheInterface::class);
		$cache->method('get')
			->willReturnCallback(function (string $key, callable $callback) {
				$item = $this->createMock(ItemInterface::class);
				$item->method('expiresAfter');

				// Simulate that callback is called but return existing count
				return 100;
			});

		$cache->expects($this->never())->method('delete');

		$cacheAdapter = new SymfonyContractsRateLimitCache($cache);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Too Many Requests. Limit: 100 per 60s');

		$cacheAdapter->increment('test_key', 100, 60);
	}

	#[Test]
	public function incrementAllowsLastRequest(): void
	{
		$cache = $this->createMock(CacheInterface::class);

		$callCount = 0;
		$cache->method('get')
			->willReturnCallback(function (string $key, callable $callback) use (&$callCount) {
				$callCount++;
				$item = $this->createMock(ItemInterface::class);
				$item->method('expiresAfter');

				if ($callCount === 1) {
					return 99; // Current count
				}

				return $callback($item);
			});

		$cache->expects($this->once())->method('delete');

		$cacheAdapter = new SymfonyContractsRateLimitCache($cache);
		$count = $cacheAdapter->increment('test_key', 100, 60);

		self::assertSame(100, $count);
	}

}
