<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Psr\Cache\CacheItemPoolInterface;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\RateLimit\Psr6RateLimitCache;
use Sabservis\Api\Middleware\RateLimit\RateLimitCache;
use Sabservis\Api\Middleware\RateLimit\SymfonyContractsRateLimitCache;
use Sabservis\Api\Utils\ClientIpResolver;
use Sabservis\Api\Utils\IpMatcher;
use Symfony\Contracts\Cache\CacheInterface;
use function array_key_exists;
use function floor;
use function max;
use function md5;
use function sprintf;
use function str_contains;
use function strtolower;
use function time;

/**
 * Rate limiting middleware with support for per-IP limits.
 *
 * Features:
 * - Global default limit
 * - Per-IP specific limits (exact match or CIDR notation)
 * - Per-user limits (when authenticated)
 * - Rate limit headers in response (X-RateLimit-*)
 * - Sliding window algorithm for accurate limiting
 * - Trusted proxy support for accurate client IP detection
 *
 * For best atomicity, use CacheItemPoolInterface (PSR-6) or Psr6RateLimitCache.
 * With CacheInterface, there's a small race condition window under high concurrency.
 *
 * SECURITY: When behind a reverse proxy (nginx, Cloudflare, etc.), configure
 * trustedProxies to enable X-Forwarded-For parsing. Without this, all users
 * behind the proxy share the same rate limit (proxy's IP).
 */
#[MiddlewarePriority(450)]
final class RateLimitMiddleware implements Middleware
{

	private readonly RateLimitCache $cache;

	/**
	 * @param RateLimitCache|CacheInterface|CacheItemPoolInterface $cache Cache backend
	 * @param int $maxRequests Maximum requests per window (default: 1_000)
	 * @param int $windowSeconds Time window in seconds (default: 60)
	 * @param array<string, array{maxRequests: int, windowSeconds?: int}> $ipLimits Per-IP limits
	 * @param array<string> $trustedProxies List of trusted proxy IPs (enables X-Forwarded-For parsing)
	 * @param string $clientIpHeader Header name for client IP (default: X-Forwarded-For)
	 */
	public function __construct(
		RateLimitCache|CacheInterface|CacheItemPoolInterface $cache,
		private readonly int $maxRequests = 1_000,
		private readonly int $windowSeconds = 60,
		private readonly array $ipLimits = [],
		private readonly array $trustedProxies = [],
		private readonly string $clientIpHeader = 'X-Forwarded-For',
	)
	{
		$this->cache = $this->wrapCache($cache);
	}

	/**
	 * Wrap cache in appropriate adapter if needed.
	 */
	private function wrapCache(RateLimitCache|CacheInterface|CacheItemPoolInterface $cache): RateLimitCache
	{
		if ($cache instanceof RateLimitCache) {
			return $cache;
		}

		if ($cache instanceof CacheItemPoolInterface) {
			return new Psr6RateLimitCache($cache);
		}

		return new SymfonyContractsRateLimitCache($cache);
	}

	/**
	 * @return array{maxRequests: int, windowSeconds: int}
	 */
	private function getLimitsForIp(string $ip): array
	{
		// Check for exact IP match
		if (array_key_exists($ip, $this->ipLimits)) {
			return [
				'maxRequests' => $this->ipLimits[$ip]['maxRequests'],
				'windowSeconds' => $this->ipLimits[$ip]['windowSeconds'] ?? $this->windowSeconds,
			];
		}

		// Check for CIDR notation (e.g., 192.168.1.0/24)
		foreach ($this->ipLimits as $cidr => $limits) {
			if (str_contains($cidr, '/') && IpMatcher::matchesCidr($ip, $cidr)) {
				return [
					'maxRequests' => $limits['maxRequests'],
					'windowSeconds' => $limits['windowSeconds'] ?? $this->windowSeconds,
				];
			}
		}

		// Default global limits
		return [
			'maxRequests' => $this->maxRequests,
			'windowSeconds' => $this->windowSeconds,
		];
	}

	/**
	 * Get unique identifier for rate limiting.
	 * Prefers authenticated user ID, falls back to client IP.
	 */
	private function getClientIdentifier(ApiRequest $request, string $clientIp): string
	{
		// Prefer authenticated user, fallback to IP
		$user = $request->getAttribute('user');

		if ($user !== null && isset($user->id)) {
			return 'user:' . $user->id;
		}

		return 'ip:' . $clientIp;
	}

	/**
	 * Get client IP address, respecting trusted proxies.
	 *
	 * SECURITY: Only parses X-Forwarded-For when the direct connection
	 * is from a trusted proxy. This prevents IP spoofing attacks.
	 *
	 * X-Forwarded-For format: "client, proxy1, proxy2, ..."
	 * - When behind trusted proxy, we take the leftmost (original client) IP
	 * - We validate that the extracted IP is a valid IP address
	 */
	private function getClientIp(ApiRequest $request): string
	{
		$remoteAddr = (string) $request->getServerParam('REMOTE_ADDR', 'unknown');

		// If no trusted proxies configured, always use REMOTE_ADDR
		if ($this->trustedProxies === []) {
			return $remoteAddr;
		}

		// Check if direct connection is from a trusted proxy
		if (!$this->isTrustedProxy($remoteAddr)) {
			return $remoteAddr;
		}

		// Parse X-Forwarded-For (or configured header) using ClientIpResolver utility
		$forwardedFor = $request->getHeaderLine(strtolower($this->clientIpHeader));
		$clientIp = ClientIpResolver::extractFromForwardedFor($forwardedFor, $this->trustedProxies);

		// Validate the extracted IP
		if ($clientIp !== null && IpMatcher::isValid($clientIp)) {
			return $clientIp;
		}

		// Fallback to REMOTE_ADDR if extraction failed or header was empty
		return $remoteAddr;
	}

	/**
	 * Check if an IP is in the trusted proxies list.
	 * Supports exact match and CIDR notation.
	 */
	private function isTrustedProxy(string $ip): bool
	{
		return IpMatcher::matchesAny($ip, $this->trustedProxies);
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		$ip = $this->getClientIp($request);
		$identifier = $this->getClientIdentifier($request, $ip);

		// Get limits for this client (IP-specific or global)
		$limits = $this->getLimitsForIp($ip);
		$maxRequests = $limits['maxRequests'];
		$windowSeconds = $limits['windowSeconds'];

		// Use sliding window with time-based key for better distribution
		$windowId = (int) floor(time() / $windowSeconds);
		$key = sprintf('rate_limit_%s_w%d', md5($identifier), $windowId);
		$resetTime = ($windowId + 1) * $windowSeconds;

		$newCount = $this->cache->increment($key, $maxRequests, $windowSeconds);

		// Execute next middleware
		$response = $next($request, $response);

		// Add rate limit headers
		return $response
			->withHeader('X-RateLimit-Limit', (string) $maxRequests)
			->withHeader('X-RateLimit-Remaining', (string) max(0, $maxRequests - $newCount))
			->withHeader('X-RateLimit-Reset', (string) $resetTime);
	}

}
