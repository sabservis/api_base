<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware\RateLimit;

use Psr\Cache\CacheItemPoolInterface;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use function is_int;
use function sprintf;

/**
 * Rate limit cache adapter using PSR-6 CacheItemPoolInterface.
 *
 * More reliable under high concurrency than Symfony Contracts.
 */
final class Psr6RateLimitCache implements RateLimitCache
{

	/**
	 * Buffer added to cache expiry to handle clock drift between servers.
	 */
	private const CACHE_EXPIRY_BUFFER_SECONDS = 5;

	public function __construct(private readonly CacheItemPoolInterface $cache)
	{
	}

	public function increment(string $key, int $maxRequests, int $windowSeconds): int
	{
		$item = $this->cache->getItem($key);
		$count = 0;

		if ($item->isHit()) {
			$cachedValue = $item->get();
			$count = is_int($cachedValue) ? $cachedValue : 0;
		}

		// Check if limit exceeded BEFORE incrementing
		if ($count >= $maxRequests) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::TOO_MANY_REQUESTS, $maxRequests, $windowSeconds),
				429,
			);
		}

		// Increment and save
		$newCount = $count + 1;
		$item->set($newCount);
		$item->expiresAfter($windowSeconds + self::CACHE_EXPIRY_BUFFER_SECONDS);
		$this->cache->save($item);

		return $newCount;
	}

}
