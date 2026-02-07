<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\RequestSizeLimitMiddleware;
use function str_repeat;

final class RequestSizeLimitMiddlewareTest extends TestCase
{

	#[Test]
	public function allowsRequestUnderLimit(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 1_024);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('a', 500),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function rejectsRequestOverLimitByContentLength(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 100);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			headers: ['Content-Length' => '500'],
			body: '', // Body not actually read yet
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Payload Too Large');

		$middleware($request, $response, $next);
	}

	#[Test]
	public function rejectsRequestOverLimitByActualSize(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 100);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('x', 200),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Payload Too Large');

		$middleware($request, $response, $next);
	}

	#[Test]
	public function returns413StatusCode(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 10);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('x', 100),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		try {
			$middleware($request, $response, $next);
			self::fail('Expected ClientErrorException');
		} catch (ClientErrorException $e) {
			self::assertSame(413, $e->getCode());
		}
	}

	#[Test]
	public function allowsExactlyAtLimit(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 100);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('a', 100),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function allowsEmptyBody(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 100);

		$request = new ApiRequest(method: 'GET', uri: '/', body: '');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function formatsMessageWithMB(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 10 * 1_024 * 1_024); // 10MB

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			headers: ['Content-Length' => '20000000'], // 20MB
			body: '',
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		try {
			$middleware($request, $response, $next);
			self::fail('Expected ClientErrorException');
		} catch (ClientErrorException $e) {
			self::assertStringContainsString('10 MB', $e->getMessage());
		}
	}

	#[Test]
	public function ignoresNonNumericContentLength(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 100);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			headers: ['Content-Length' => 'invalid'],
			body: str_repeat('a', 50),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Non-numeric Content-Length is treated as 0, so actual body size is checked
		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function rejectsWhenActualBodyExceedsLimitDespiteMissingContentLength(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 50);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			// No Content-Length header
			body: str_repeat('x', 100),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Payload Too Large');

		$middleware($request, $response, $next);
	}

	#[Test]
	public function rejectsNegativeContentLength(): void
	{
		$middleware = new RequestSizeLimitMiddleware(maxBodySize: 100);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			headers: ['Content-Length' => '-500'],
			body: str_repeat('x', 150),
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		// Negative Content-Length is treated as 0, so actual body size is checked
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Payload Too Large');

		$middleware($request, $response, $next);
	}

}
