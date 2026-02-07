<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\EnforceHttpsMiddleware;

final class EnforceHttpsMiddlewareTest extends TestCase
{

	#[Test]
	public function rejectsHttpRequest(): void
	{
		$middleware = new EnforceHttpsMiddleware();

		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/api');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertSame(400, $result->getStatusCode());
		self::assertStringContainsString('https', $result->getBody());
	}

	#[Test]
	public function allowsHttpsRequest(): void
	{
		$middleware = new EnforceHttpsMiddleware();

		$request = new ApiRequest(
			method: 'GET',
			uri: 'https://example.com/api',
			serverParams: ['HTTPS' => 'on'],
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200)->writeBody('OK');

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('OK', $result->getBody());
	}

	#[Test]
	public function passesRequestToNextMiddleware(): void
	{
		$middleware = new EnforceHttpsMiddleware();

		$nextCalled = false;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$nextCalled): ApiResponse {
			$nextCalled = true;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(
			method: 'GET',
			uri: 'https://example.com/api',
			serverParams: ['HTTPS' => 'on'],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertTrue($nextCalled);
	}

	#[Test]
	public function doesNotCallNextOnHttpRequest(): void
	{
		$middleware = new EnforceHttpsMiddleware();

		$nextCalled = false;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$nextCalled): ApiResponse {
			$nextCalled = true;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/api');
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertFalse($nextCalled);
	}

}
