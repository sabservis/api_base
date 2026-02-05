<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware\RateLimit;

use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use function assert;
use function is_int;
use function sprintf;
use const INF;

/**
 * Rate limit cache adapter using Symfony Cache Contracts.
 *
 * Note: Has a small race condition window under very high concurrency.
 * For better atomicity, prefer Psr6RateLimitCache.
 */
final class SymfonyContractsRateLimitCache implements RateLimitCache
{

	/**
	 * Buffer added to cache expiry to handle clock drift between servers.
	 */
	private const CACHE_EXPIRY_BUFFER_SECONDS = 5;

	public function __construct(private readonly CacheInterface $cache)
	{
	}

	public function increment(string $key, int $maxRequests, int $windowSeconds): int
	{
		$count = $this->cache->get($key, static function (ItemInterface $item) use ($windowSeconds): int {
			$item->expiresAfter($windowSeconds + self::CACHE_EXPIRY_BUFFER_SECONDS);

			return 0;
		});
		assert(is_int($count));

		// Check if limit exceeded BEFORE incrementing
		if ($count >= $maxRequests) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::TOO_MANY_REQUESTS, $maxRequests, $windowSeconds),
				429,
			);
		}

		// Increment - force cache update
		$newCount = $count + 1;
		$this->cache->delete($key);
		$this->cache->get($key, static function (ItemInterface $item) use ($windowSeconds, $newCount): int {
			$item->expiresAfter($windowSeconds + self::CACHE_EXPIRY_BUFFER_SECONDS);

			return $newCount;
		}, INF);

		return $newCount;
	}

}
