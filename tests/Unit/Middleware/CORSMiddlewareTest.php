<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\CORSMiddleware;

final class CORSMiddlewareTest extends TestCase
{

	#[Test]
	public function preflightRequest(): void
	{
		$middleware = new CORSMiddleware();
		$request = new ApiRequest(method: 'OPTIONS', uri: '/');
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame(204, $result->getStatusCode());
		self::assertSame('*', $result->getHeader('access-control-allow-origin'));
		self::assertNotNull($result->getHeader('access-control-allow-methods'));
		self::assertNotNull($result->getHeader('access-control-allow-headers'));
	}

	#[Test]
	public function normalRequestWithCorsHeaders(): void
	{
		$middleware = new CORSMiddleware();
		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);
		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('*', $result->getHeader('access-control-allow-origin'));
	}

	#[Test]
	public function allowedOrigins(): void
	{
		$middleware = new CORSMiddleware(
			allowedOrigins: ['https://example.com', 'https://test.com'],
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['origin' => 'https://example.com'],
		);
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('https://example.com', $result->getHeader('access-control-allow-origin'));
		self::assertSame('Origin', $result->getHeader('vary'));
	}

	#[Test]
	public function disallowedOrigin(): void
	{
		$middleware = new CORSMiddleware(
			allowedOrigins: ['https://example.com'],
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['origin' => 'https://evil.com'],
		);
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertNull($result->getHeader('access-control-allow-origin'));
	}

	#[Test]
	public function allowCredentialsWithExplicitOrigins(): void
	{
		$middleware = new CORSMiddleware(
			allowedOrigins: ['https://example.com', 'https://trusted.com'],
			allowCredentials: true,
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['origin' => 'https://example.com'],
		);
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('true', $result->getHeader('access-control-allow-credentials'));
		self::assertSame('https://example.com', $result->getHeader('access-control-allow-origin'));
		self::assertSame('Origin', $result->getHeader('vary'));
	}

	#[Test]
	public function allowCredentialsRejectsUnknownOrigin(): void
	{
		$middleware = new CORSMiddleware(
			allowedOrigins: ['https://trusted.com'],
			allowCredentials: true,
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['origin' => 'https://evil.com'],
		);
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		// Origin not allowed, no CORS headers
		self::assertNull($result->getHeader('access-control-allow-origin'));
		self::assertNull($result->getHeader('access-control-allow-credentials'));
	}

	// === Security Validation Tests ===

	#[Test]
	public function throwsExceptionForCredentialsWithEmptyOrigins(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('allowCredentials cannot be used with empty allowedOrigins');

		new CORSMiddleware(
			allowedOrigins: [],
			allowCredentials: true,
		);
	}

	#[Test]
	public function throwsExceptionForCredentialsWithWildcardOrigin(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('allowCredentials cannot be used with wildcard origin "*"');

		new CORSMiddleware(
			allowedOrigins: ['*'],
			allowCredentials: true,
		);
	}

	#[Test]
	public function throwsExceptionForCredentialsWithWildcardAmongOrigins(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('allowCredentials cannot be used with wildcard origin "*"');

		new CORSMiddleware(
			allowedOrigins: ['https://example.com', '*', 'https://other.com'],
			allowCredentials: true,
		);
	}

	#[Test]
	public function allowsEmptyOriginsWithoutCredentials(): void
	{
		// This should NOT throw - wildcard without credentials is safe
		$middleware = new CORSMiddleware(
			allowedOrigins: [],
			allowCredentials: false,
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['origin' => 'https://any-origin.com'],
		);
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('*', $result->getHeader('access-control-allow-origin'));
		self::assertNull($result->getHeader('access-control-allow-credentials'));
	}

	#[Test]
	public function allowsWildcardOriginWithoutCredentials(): void
	{
		// Explicit '*' in origins without credentials is allowed (though redundant)
		$middleware = new CORSMiddleware(
			allowedOrigins: ['*'],
			allowCredentials: false,
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['origin' => 'https://any-origin.com'],
		);
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		// '*' is in allowedOrigins but doesn't match the actual origin
		// This returns null because 'https://any-origin.com' !== '*'
		self::assertNull($result->getHeader('access-control-allow-origin'));
	}

	#[Test]
	public function customMethods(): void
	{
		$middleware = new CORSMiddleware(
			allowedMethods: ['GET', 'POST'],
		);

		$request = new ApiRequest(method: 'OPTIONS', uri: '/');
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('GET, POST', $result->getHeader('access-control-allow-methods'));
	}

	#[Test]
	public function customHeaders(): void
	{
		$middleware = new CORSMiddleware(
			allowedHeaders: ['X-Custom-Header', 'Authorization'],
		);

		$request = new ApiRequest(method: 'OPTIONS', uri: '/');
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('X-Custom-Header, Authorization', $result->getHeader('access-control-allow-headers'));
	}

	#[Test]
	public function maxAge(): void
	{
		$middleware = new CORSMiddleware(
			maxAge: 3600,
		);

		$request = new ApiRequest(method: 'OPTIONS', uri: '/');
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('3600', $result->getHeader('access-control-max-age'));
	}

	#[Test]
	public function exposedHeaders(): void
	{
		$middleware = new CORSMiddleware(
			exposedHeaders: ['X-Request-Id', 'X-Total-Count'],
		);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;
		$result = $middleware($request, $response, $next);

		self::assertSame('X-Request-Id, X-Total-Count', $result->getHeader('access-control-expose-headers'));
	}

}
