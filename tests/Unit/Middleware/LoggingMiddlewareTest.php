<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\LoggingMiddleware;

final class LoggingMiddlewareTest extends TestCase
{

	#[Test]
	public function logsRequestUrl(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with($this->stringContains('/api/users'));

		$middleware = new LoggingMiddleware($logger);

		$request = new ApiRequest(method: 'GET', uri: 'https://example.com/api/users');
		$response = new ApiResponse();
		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function passesRequestToNext(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$middleware = new LoggingMiddleware($logger);

		$nextCalled = false;
		$next = function (ApiRequest $req, ApiResponse $res) use (&$nextCalled): ApiResponse {
			$nextCalled = true;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertTrue($nextCalled);
	}

	#[Test]
	public function returnsResponseFromNext(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$middleware = new LoggingMiddleware($logger);

		$next = fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res
			->withStatus(201)
			->withHeader('X-Custom', 'value');

		$request = new ApiRequest(method: 'POST', uri: '/');
		$response = new ApiResponse();

		$result = $middleware($request, $response, $next);

		self::assertSame(201, $result->getStatusCode());
		self::assertSame('value', $result->getHeader('x-custom'));
	}

	#[Test]
	public function logsBeforeCallingNext(): void
	{
		$logCalled = false;
		$nextCalled = false;
		$logCalledBeforeNext = false;

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('info')
			->willReturnCallback(function () use (&$logCalled, &$nextCalled, &$logCalledBeforeNext): void {
				$logCalled = true;
				$logCalledBeforeNext = !$nextCalled;
			});

		$middleware = new LoggingMiddleware($logger);

		$next = function (ApiRequest $req, ApiResponse $res) use (&$nextCalled): ApiResponse {
			$nextCalled = true;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertTrue($logCalled);
		self::assertTrue($logCalledBeforeNext);
	}

}
