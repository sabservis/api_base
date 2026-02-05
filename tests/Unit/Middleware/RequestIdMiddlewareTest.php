<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\RequestIdMiddleware;

final class RequestIdMiddlewareTest extends TestCase
{

	#[Test]
	public function generatesRequestIdWhenNotPresent(): void
	{
		$middleware = new RequestIdMiddleware();
		$request = ApiRequest::fromGlobals();
		$response = new ApiResponse();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res;
		};

		$result = $middleware($request, $response, $next);

		// Request should have requestId attribute
		self::assertNotNull($capturedRequest);
		$requestId = $capturedRequest->getAttribute(RequestIdMiddleware::AttributeName);
		self::assertNotNull($requestId);
		self::assertMatchesRegularExpression(
			'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/',
			$requestId,
			'Generated request ID should be valid UUID v4',
		);

		// Response should have X-Request-ID header
		self::assertTrue($result->hasHeader(RequestIdMiddleware::HeaderName));
		self::assertSame($requestId, $result->getHeader(RequestIdMiddleware::HeaderName));
	}

	#[Test]
	public function usesExistingRequestIdFromHeader(): void
	{
		$middleware = new RequestIdMiddleware();
		$existingId = 'existing-request-id-12345';
		$request = ApiRequest::fromGlobals()->withHeader('X-Request-ID', $existingId);
		$response = new ApiResponse();

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res;
		};

		$result = $middleware($request, $response, $next);

		// Should use existing ID, not generate new one
		self::assertNotNull($capturedRequest);
		self::assertSame($existingId, $capturedRequest->getAttribute(RequestIdMiddleware::AttributeName));
		self::assertSame($existingId, $result->getHeader(RequestIdMiddleware::HeaderName));
	}

	#[Test]
	public function generatedUuidsAreUnique(): void
	{
		$middleware = new RequestIdMiddleware();
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;

		$ids = [];
		for ($i = 0; $i < 100; $i++) {
			$request = ApiRequest::fromGlobals();
			$result = $middleware($request, $response, $next);
			$ids[] = $result->getHeader(RequestIdMiddleware::HeaderName);
		}

		// All IDs should be unique
		self::assertCount(100, array_unique($ids));
	}

	#[Test]
	public function requestIdIsAccessibleInController(): void
	{
		$middleware = new RequestIdMiddleware();
		$request = ApiRequest::fromGlobals();
		$response = new ApiResponse();

		$requestIdInController = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$requestIdInController): ApiResponse {
			// Simulate controller accessing request ID
			$requestIdInController = $req->getAttribute('requestId');

			return $res;
		};

		$middleware($request, $response, $next);

		self::assertNotNull($requestIdInController);
		self::assertIsString($requestIdInController);
	}

}
