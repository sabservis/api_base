<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\RequestIdMiddleware;
use function array_unique;
use function str_repeat;

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

	/**
	 * @return array<string, array{string}>
	 */
	public static function validRequestIds(): array
	{
		return [
			'uuid' => ['550e8400-e29b-41d4-a716-446655440000'],
			'ulid' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
			'hex' => ['4bf92f3577b34da6a3ce929d0e0e4736'],
			'aws trace' => ['Root=1-5759e988-bd862e3fe1be46a994272793'],
			'dotted' => ['req.123:abc_def'],
		];
	}

	#[Test]
	#[DataProvider('validRequestIds')]
	public function acceptsCommonRequestIdFormats(string $requestId): void
	{
		[$attribute, $header] = $this->process($requestId);

		self::assertSame($requestId, $attribute);
		self::assertSame($requestId, $header);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function unsafeRequestIds(): array
	{
		return [
			'newline (log injection)' => ["abc\n2024-01-01 INFO user=admin login ok"],
			'carriage return' => ["abc\rdef"],
			'trailing newline' => ["abc\n"],
			'ansi escape' => ["abc\e[31mred"],
			'space' => ['abc def'],
			'too long' => [str_repeat('a', 129)],
			'html' => ['<script>'],
		];
	}

	#[Test]
	#[DataProvider('unsafeRequestIds')]
	public function replacesUnsafeRequestIdWithGeneratedUuid(string $requestId): void
	{
		[$attribute, $header] = $this->process($requestId);

		self::assertNotSame($requestId, $attribute);
		self::assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$attribute,
		);
		self::assertSame($attribute, $header);
	}

	#[Test]
	public function acceptsMaximumLengthRequestId(): void
	{
		$requestId = str_repeat('a', 128);

		self::assertSame($requestId, $this->process($requestId)[0]);
	}

	/**
	 * @return array{string, string|null} [request attribute, response header]
	 */
	private function process(string $requestId): array
	{
		$captured = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$captured): ApiResponse {
			$captured = $req->getAttribute(RequestIdMiddleware::AttributeName);

			return $res;
		};

		$request = new ApiRequest(method: 'GET', uri: '/', headers: [RequestIdMiddleware::HeaderName => $requestId]);
		$response = (new RequestIdMiddleware())($request, new ApiResponse(), $next);
		self::assertIsString($captured);

		return [$captured, $response->getHeader(RequestIdMiddleware::HeaderName)];
	}

}
