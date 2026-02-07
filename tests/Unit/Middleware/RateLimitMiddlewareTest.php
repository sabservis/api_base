<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\RateLimitMiddleware;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RateLimitMiddlewareTest extends TestCase
{

	#[Test]
	public function allowsRequestUnderLimit(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware($cache, maxRequests: 10, windowSeconds: 60);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function addsRateLimitHeaders(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware($cache, maxRequests: 100, windowSeconds: 60);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertSame('100', $result->getHeader('x-ratelimit-limit'));
		self::assertSame('99', $result->getHeader('x-ratelimit-remaining'));
		self::assertNotNull($result->getHeader('x-ratelimit-reset'));
	}

	#[Test]
	public function throwsWhenLimitExceeded(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware($cache, maxRequests: 2, windowSeconds: 60);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// First two requests should pass
		$middleware($request, $response, $next);
		$middleware($request, $response, $next);

		// Third request should fail
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Too Many Requests');

		$middleware($request, $response, $next);
	}

	#[Test]
	public function differentIpsHaveSeparateLimits(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware($cache, maxRequests: 1, windowSeconds: 60);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// First IP
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '192.168.1.1'],
		);
		$result1 = $middleware($request1, $response, $next);
		self::assertSame(200, $result1->getStatusCode());

		// Second IP - should have its own limit
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '192.168.1.2'],
		);
		$result2 = $middleware($request2, $response, $next);
		self::assertSame(200, $result2->getStatusCode());
	}

	#[Test]
	public function ipSpecificLimitsOverrideDefault(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			ipLimits: [
				'10.0.0.1' => ['maxRequests' => 100],
			],
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// IP with custom limit
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$result = $middleware($request, $response, $next);

		// Should have 100 limit, not 1
		self::assertSame('100', $result->getHeader('x-ratelimit-limit'));
	}

	#[Test]
	public function cidrMatchingWorks(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			ipLimits: [
				'192.168.1.0/24' => ['maxRequests' => 500],
			],
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// IP within CIDR range
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '192.168.1.50'],
		);

		$result = $middleware($request, $response, $next);

		// Should use CIDR limit
		self::assertSame('500', $result->getHeader('x-ratelimit-limit'));
	}

	#[Test]
	public function remainingDecrementsCorrectly(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware($cache, maxRequests: 5, windowSeconds: 60);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result1 = $middleware($request, $response, $next);
		self::assertSame('4', $result1->getHeader('x-ratelimit-remaining'));

		$result2 = $middleware($request, $response, $next);
		self::assertSame('3', $result2->getHeader('x-ratelimit-remaining'));

		$result3 = $middleware($request, $response, $next);
		self::assertSame('2', $result3->getHeader('x-ratelimit-remaining'));
	}

	// === Trusted Proxy Tests ===

	#[Test]
	public function usesRemoteAddrWithoutTrustedProxies(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: [], // No trusted proxies
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Request with X-Forwarded-For but no trusted proxies configured
		// Should use REMOTE_ADDR, not the forwarded IP
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '1.2.3.4'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$middleware($request1, $response, $next);

		// Same REMOTE_ADDR should hit the limit
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '5.6.7.8'], // Different forwarded IP
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'], // Same direct IP
		);

		$this->expectException(ClientErrorException::class);
		$middleware($request2, $response, $next);
	}

	#[Test]
	public function parsesXForwardedForWithTrustedProxy(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.1'], // Proxy is trusted
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Request through trusted proxy
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '1.2.3.4'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$middleware($request1, $response, $next);

		// Different client IP through same proxy - should have separate limit
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '5.6.7.8'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$result = $middleware($request2, $response, $next);

		// Should succeed because it's a different client IP
		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function ignoresXForwardedForFromUntrustedProxy(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: ['192.168.1.1'], // Only this proxy is trusted
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Request from untrusted IP with spoofed X-Forwarded-For
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '1.2.3.4'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'], // Not in trusted list
		);
		$middleware($request1, $response, $next);

		// Same attacker tries with different spoofed IP
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '5.6.7.8'], // Spoofed
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'], // Same real IP
		);

		// Should fail - X-Forwarded-For was ignored because proxy not trusted
		$this->expectException(ClientErrorException::class);
		$middleware($request2, $response, $next);
	}

	#[Test]
	public function handlesMultipleProxiesInChain(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.1', '10.0.0.2'], // Both proxies trusted
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Chain: client -> proxy2 -> proxy1 -> server
		// X-Forwarded-For: client_ip, proxy2_ip
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '1.2.3.4, 10.0.0.2'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$middleware($request1, $response, $next);

		// Different client through same chain
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '5.6.7.8, 10.0.0.2'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$result = $middleware($request2, $response, $next);

		// Should succeed - different client IP extracted
		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function trustedProxyCidrMatching(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.0/8'], // Entire 10.x.x.x range trusted
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Request from any 10.x.x.x proxy
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '1.2.3.4'],
			serverParams: ['REMOTE_ADDR' => '10.255.255.255'],
		);
		$middleware($request1, $response, $next);

		// Different client
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '5.6.7.8'],
			serverParams: ['REMOTE_ADDR' => '10.1.2.3'],
		);
		$result = $middleware($request2, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function handlesInvalidIpInXForwardedFor(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 10,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.1'],
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Invalid IP in X-Forwarded-For - should fallback to REMOTE_ADDR
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => 'not-an-ip, also-invalid'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$result = $middleware($request, $response, $next);

		// Should still work, using REMOTE_ADDR as fallback
		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function handlesEmptyXForwardedFor(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 10,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.1'],
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Empty X-Forwarded-For
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => ''],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function supportsCustomClientIpHeader(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.1'],
			clientIpHeader: 'CF-Connecting-IP', // Cloudflare header
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Request with Cloudflare header
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['CF-Connecting-IP' => '1.2.3.4'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$middleware($request1, $response, $next);

		// Different client via same header
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['CF-Connecting-IP' => '5.6.7.8'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		$result = $middleware($request2, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function preventsIpSpoofingAttack(): void
	{
		$cache = new ArrayAdapter();
		$middleware = new RateLimitMiddleware(
			$cache,
			maxRequests: 1,
			windowSeconds: 60,
			trustedProxies: ['10.0.0.1'], // Only internal proxy trusted
		);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Attacker sends request directly (not through proxy)
		// trying to spoof X-Forwarded-For
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '1.1.1.1'], // Spoofed
			serverParams: ['REMOTE_ADDR' => '203.0.113.50'], // Attacker's real IP
		);
		$middleware($request1, $response, $next);

		// Same attacker tries again with different spoofed IP
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '2.2.2.2'], // Different spoofed IP
			serverParams: ['REMOTE_ADDR' => '203.0.113.50'], // Same real IP
		);

		// Should fail because we use REMOTE_ADDR (not spoofed header)
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Too Many Requests');
		$middleware($request2, $response, $next);
	}

}
