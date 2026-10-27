<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\MethodOverrideMiddleware;

final class MethodOverrideMiddlewareTest extends TestCase
{

	#[Test]
	public function overridesMethodWithHeader(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'DELETE'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertSame('DELETE', $capturedRequest->getMethod());
	}

	#[Test]
	public function keepsOriginalMethodWithoutHeader(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(method: 'POST', uri: '/api/users');
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertSame('POST', $capturedRequest->getMethod());
	}

	#[Test]
	public function keepsOriginalMethodWithEmptyHeader(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users',
			headers: ['x-http-method-override' => ''],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertSame('POST', $capturedRequest->getMethod());
	}

	#[Test]
	public function overrideToPut(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'PUT'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertSame('PUT', $capturedRequest->getMethod());
	}

	#[Test]
	public function overrideToPatch(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'PATCH'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertSame('PATCH', $capturedRequest->getMethod());
	}

	#[Test]
	public function passesResponseFromNext(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res
			->withStatus(204)
			->withHeader('X-Custom', 'value');

		$request = new ApiRequest(method: 'POST', uri: '/');
		$response = new ApiResponse();

		$result = $middleware($request, $response, $next);

		self::assertSame(204, $result->getStatusCode());
		self::assertSame('value', $result->getHeader('x-custom'));
	}

	#[Test]
	public function ignoresInvalidOverrideMethod(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'INVALID'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		// Invalid method should be silently ignored - keeps original POST
		self::assertSame('POST', $capturedRequest->getMethod());
	}

	#[Test]
	public function ignoresArbitraryStringOverride(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		// Attempt to inject arbitrary method (security test)
		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/admin/users/1',
			headers: ['x-http-method-override' => 'DESTROY'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		// Arbitrary method should be ignored
		self::assertSame('POST', $capturedRequest->getMethod());
	}

	#[Test]
	public function normalizesMethodToUppercase(): void
	{
		$middleware = new MethodOverrideMiddleware();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'delete'], // lowercase
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		// Should normalize to uppercase DELETE
		self::assertSame('DELETE', $capturedRequest->getMethod());
	}

	#[Test]
	public function respectsCustomAllowedMethods(): void
	{
		// Only allow PUT and PATCH, not DELETE
		$middleware = new MethodOverrideMiddleware(['PUT', 'PATCH']);

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'DELETE'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		// DELETE not in allowed list, should keep POST
		self::assertSame('POST', $capturedRequest->getMethod());
	}

	#[Test]
	public function allowsMethodFromCustomList(): void
	{
		$middleware = new MethodOverrideMiddleware(['PUT', 'PATCH']);

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'POST',
			uri: '/api/users/1',
			headers: ['x-http-method-override' => 'PUT'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		// PUT is in allowed list
		self::assertSame('PUT', $capturedRequest->getMethod());
	}

	#[Test]
	public function getRequestCannotBeOverridden(): void
	{
		self::assertSame('GET', $this->methodAfterOverride(new MethodOverrideMiddleware(), 'GET', 'DELETE'));
	}

	#[Test]
	public function nonPostRequestCannotBeOverridden(): void
	{
		self::assertSame('PUT', $this->methodAfterOverride(new MethodOverrideMiddleware(), 'PUT', 'DELETE'));
		self::assertSame('HEAD', $this->methodAfterOverride(new MethodOverrideMiddleware(), 'HEAD', 'DELETE'));
	}

	#[Test]
	public function postRequestCanBeOverridden(): void
	{
		self::assertSame('DELETE', $this->methodAfterOverride(new MethodOverrideMiddleware(), 'POST', 'DELETE'));
	}

	#[Test]
	public function overridableMethodsCanBeConfigured(): void
	{
		$middleware = new MethodOverrideMiddleware(overridableMethods: ['POST', 'GET']);

		self::assertSame('DELETE', $this->methodAfterOverride($middleware, 'GET', 'DELETE'));
	}

	private function methodAfterOverride(MethodOverrideMiddleware $middleware, string $method, string $override): string
	{
		$captured = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$captured): ApiResponse {
			$captured = $req;

			return $res;
		};

		$request = new ApiRequest(
			method: $method,
			uri: '/api/users/1',
			headers: [MethodOverrideMiddleware::OverrideHeader => $override],
		);
		$middleware($request, new ApiResponse(), $next);
		self::assertInstanceOf(ApiRequest::class, $captured);

		return $captured->getMethod();
	}

}
