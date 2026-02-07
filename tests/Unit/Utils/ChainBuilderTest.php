<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Utils\ChainBuilder;

final class ChainBuilderTest extends TestCase
{

	#[Test]
	public function createWithNoMiddlewareThrows(): void
	{
		$chain = new ChainBuilder();

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('At least one middleware is needed');

		$chain->create();
	}

	#[Test]
	public function singleMiddleware(): void
	{
		$chain = new ChainBuilder();
		$chain->add(
			static fn (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse => $response->withHeader(
				'X-Test',
				'value',
			),
		);

		$handler = $chain->create();

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$result = $handler($request, $response);

		self::assertSame('value', $result->getHeader('x-test'));
	}

	#[Test]
	public function middlewareChainOrder(): void
	{
		$chain = new ChainBuilder();
		$order = [];

		$chain->add(
			static function (ApiRequest $request, ApiResponse $response, callable $next) use (&$order): ApiResponse {
				$order[] = 'first-before';
				$response = $next($request, $response);
				$order[] = 'first-after';

				return $response;
			},
		);

		$chain->add(
			static function (ApiRequest $request, ApiResponse $response, callable $next) use (&$order): ApiResponse {
				$order[] = 'second-before';
				$response = $next($request, $response);
				$order[] = 'second-after';

				return $response;
			},
		);

		$handler = $chain->create();

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$handler($request, $response);

		self::assertSame(['first-before', 'second-before', 'second-after', 'first-after'], $order);
	}

	#[Test]
	public function addAll(): void
	{
		$chain = new ChainBuilder();

		$middlewares = [
			static fn (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse => $next($request, $response)->withHeader(
				'X-First',
				'1',
			),
			static fn (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse => $next($request, $response)->withHeader(
				'X-Second',
				'2',
			),
		];

		$chain->addAll($middlewares);
		$handler = $chain->create();

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$result = $handler($request, $response);

		self::assertSame('1', $result->getHeader('x-first'));
		self::assertSame('2', $result->getHeader('x-second'));
	}

}
