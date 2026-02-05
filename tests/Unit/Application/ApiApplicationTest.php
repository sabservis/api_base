<?php declare(strict_types = 1);

namespace Tests\Unit\Application;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function strlen;

final class ApiApplicationTest extends TestCase
{

	#[Test]
	public function runWithExecutesMiddlewareChain(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response
				->withStatus(200)
				->withHeader('X-Custom', 'test')
				->writeBody('Hello World');
		};

		$app = new ApiApplication($chain);

		$request = new ApiRequest(method: 'GET', uri: '/');

		// Use output buffering to capture echo output
		ob_start();
		$result = $app->runWith($request);
		$output = ob_get_clean();

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('test', $result->getHeader('x-custom'));
		self::assertSame('Hello World', $output);
	}

	#[Test]
	public function runWithPassesRequestToChain(): void
	{
		$receivedRequest = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedRequest): ApiResponse {
			$receivedRequest = $request;

			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'application/json'],
		);

		ob_start();
		$app->runWith($request);
		ob_end_clean();

		self::assertSame('POST', $receivedRequest->getMethod());
		self::assertSame('/users', $receivedRequest->getUri()->getPath());
	}

	#[Test]
	public function runWithCallsNextCallback(): void
	{
		$nextCalled = false;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$nextCalled): ApiResponse {
			$response = $response->withStatus(200);
			$result = $next($request, $response);
			$nextCalled = true;

			return $result;
		};

		$app = new ApiApplication($chain);

		ob_start();
		$app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		ob_end_clean();

		self::assertTrue($nextCalled);
	}

	#[Test]
	public function exceptionBubblesUpByDefault(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			throw new RuntimeException('Test error');
		};

		$app = new ApiApplication($chain);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Test error');

		$app->runWith(new ApiRequest(method: 'GET', uri: '/'));
	}

	#[Test]
	public function catchExceptionsSuppressesException(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			throw new RuntimeException('Test error');
		};

		$app = new ApiApplication($chain);
		$app->setCatchExceptions(true);

		ob_start();
		// Should not throw
		$result = $app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		ob_end_clean();

		// Response should still be returned (default response)
		self::assertInstanceOf(ApiResponse::class, $result);
	}

	#[Test]
	public function nestedMiddlewareChain(): void
	{
		$order = [];

		// Simulate a chain where outer wraps inner
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$order): ApiResponse {
			// Outer middleware - before
			$order[] = 'outer-before';

			// Inner middleware
			$order[] = 'inner-before';
			$response = $next($request, $response);
			$order[] = 'inner-after';

			// Outer middleware - after
			$order[] = 'outer-after';

			return $response;
		};

		$app = new ApiApplication($chain);

		ob_start();
		$app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		ob_end_clean();

		self::assertSame(['outer-before', 'inner-before', 'inner-after', 'outer-after'], $order);
	}

	#[Test]
	public function middlewareCanModifyResponse(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			$response = $response
				->withStatus(201)
				->withHeader('X-Created-By', 'test');

			return $next($request, $response);
		};

		$app = new ApiApplication($chain);

		ob_start();
		$result = $app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		ob_end_clean();

		self::assertSame(201, $result->getStatusCode());
		self::assertSame('test', $result->getHeader('x-created-by'));
	}

	#[Test]
	public function middlewareCanShortCircuit(): void
	{
		$innerCalled = false;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$innerCalled): ApiResponse {
			// Don't call next - short circuit
			return $response->withStatus(401)->writeBody('Unauthorized');
		};

		$app = new ApiApplication($chain);

		ob_start();
		$result = $app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		$output = ob_get_clean();

		self::assertSame(401, $result->getStatusCode());
		self::assertSame('Unauthorized', $output);
	}

	#[Test]
	public function responseBodyCanBeResource(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test');
		file_put_contents($tempFile, 'File content here');

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use ($tempFile): ApiResponse {
			$handle = fopen($tempFile, 'r');

			return $response->withStatus(200)->withBody($handle);
		};

		$app = new ApiApplication($chain);

		ob_start();
		$app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		$output = ob_get_clean();

		self::assertSame('File content here', $output);

		unlink($tempFile);
	}

	#[Test]
	public function defaultStatusIs200(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $next($request, $response);
		};

		$app = new ApiApplication($chain);

		ob_start();
		$result = $app->runWith(new ApiRequest(method: 'GET', uri: '/'));
		ob_end_clean();

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function trustedProxiesAreAppliedToRequest(): void
	{
		$receivedRequest = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedRequest): ApiResponse {
			$receivedRequest = $request;

			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$app->setTrustedProxies(['10.0.0.0/8', '192.168.0.0/16']);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		ob_start();
		$app->runWith($request);
		ob_end_clean();

		// Request should have trusted proxies applied
		self::assertSame(['10.0.0.0/8', '192.168.0.0/16'], $receivedRequest->getTrustedProxies());
		self::assertTrue($receivedRequest->isSecured());
	}

	#[Test]
	public function trustedProxiesDefaultToEmptyArray(): void
	{
		$receivedRequest = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedRequest): ApiResponse {
			$receivedRequest = $request;

			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		ob_start();
		$app->runWith($request);
		ob_end_clean();

		// Without trusted proxies, header should be ignored
		self::assertSame([], $receivedRequest->getTrustedProxies());
		self::assertFalse($receivedRequest->isSecured());
	}

	#[Test]
	public function maxRequestBodySizeRejectsLargeRequest(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$app->setMaxRequestBodySize(100); // 100 bytes limit

		// Request with body larger than limit
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('x', 200), // 200 bytes - exceeds limit
		);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(413);

		$app->runWith($request);
	}

	#[Test]
	public function maxRequestBodySizeAllowsSmallRequest(): void
	{
		$receivedRequest = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedRequest): ApiResponse {
			$receivedRequest = $request;

			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$app->setMaxRequestBodySize(1000); // 1000 bytes limit

		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('x', 500), // 500 bytes - within limit
		);

		ob_start();
		$app->runWith($request);
		ob_end_clean();

		self::assertSame(500, strlen($receivedRequest->getContents()));
	}

	#[Test]
	public function maxRequestBodySizeDefaultsToNull(): void
	{
		$receivedRequest = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedRequest): ApiResponse {
			$receivedRequest = $request;

			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);

		// Large request should pass when no limit is set
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: str_repeat('x', 100000),
		);

		ob_start();
		$app->runWith($request);
		ob_end_clean();

		self::assertSame(100000, strlen($receivedRequest->getContents()));
	}

}
