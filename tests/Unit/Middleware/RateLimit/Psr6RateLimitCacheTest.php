<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware\RateLimit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Middleware\RateLimit\Psr6RateLimitCache;

final class Psr6RateLimitCacheTest extends TestCase
{

	#[Test]
	public function incrementFirstRequest(): void
	{
		$item = $this->createMock(CacheItemInterface::class);
		$item->method('isHit')->willReturn(false);
		$item->method('get')->willReturn(null);
		$item->expects($this->once())->method('set')->with(1);
		$item->expects($this->once())->method('expiresAfter')->with(65); // 60 + 5 buffer

		$pool = $this->createMock(CacheItemPoolInterface::class);
		$pool->method('getItem')->willReturn($item);
		$pool->expects($this->once())->method('save')->with($item);

		$cache = new Psr6RateLimitCache($pool);
		$count = $cache->increment('test_key', 100, 60);

		self::assertSame(1, $count);
	}

	#[Test]
	public function incrementExistingCounter(): void
	{
		$item = $this->createMock(CacheItemInterface::class);
		$item->method('isHit')->willReturn(true);
		$item->method('get')->willReturn(5);
		$item->expects($this->once())->method('set')->with(6);

		$pool = $this->createMock(CacheItemPoolInterface::class);
		$pool->method('getItem')->willReturn($item);
		$pool->expects($this->once())->method('save');

		$cache = new Psr6RateLimitCache($pool);
		$count = $cache->increment('test_key', 100, 60);

		self::assertSame(6, $count);
	}

	#[Test]
	public function incrementThrowsWhenLimitExceeded(): void
	{
		$item = $this->createMock(CacheItemInterface::class);
		$item->method('isHit')->willReturn(true);
		$item->method('get')->willReturn(100);

		$pool = $this->createMock(CacheItemPoolInterface::class);
		$pool->method('getItem')->willReturn($item);
		$pool->expects($this->never())->method('save');

		$cache = new Psr6RateLimitCache($pool);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Too Many Requests. Limit: 100 per 60s');

		$cache->increment('test_key', 100, 60);
	}

	#[Test]
	public function incrementAllowsLastRequest(): void
	{
		$item = $this->createMock(CacheItemInterface::class);
		$item->method('isHit')->willReturn(true);
		$item->method('get')->willReturn(99);
		$item->expects($this->once())->method('set')->with(100);

		$pool = $this->createMock(CacheItemPoolInterface::class);
		$pool->method('getItem')->willReturn($item);
		$pool->expects($this->once())->method('save');

		$cache = new Psr6RateLimitCache($pool);
		$count = $cache->increment('test_key', 100, 60);

		self::assertSame(100, $count);
	}

}
