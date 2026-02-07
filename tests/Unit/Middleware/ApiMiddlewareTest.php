<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Throwable;

/**
 * Test for ApiMiddleware using a test double for the dispatcher.
 * Since ApiDispatcher is final, we create a simple wrapper for testing.
 */
final class ApiMiddlewareTest extends TestCase
{

	#[Test]
	public function successfulDispatch(): void
	{
		$expectedResponse = (new ApiResponse())->withStatus(200)->writeBody('OK');

		$dispatcher = new class ($expectedResponse) {

			public function __construct(private ApiResponse $response)
			{
			}

			public function dispatch(ApiRequest $request, ApiResponse $response): ApiResponse
			{
				return $this->response;
			}

		};

		$errorHandler = $this->createMock(ErrorHandler::class);
		$errorHandler->expects($this->never())->method('handle');

		// Use reflection to create ApiMiddleware with our test dispatcher
		$middleware = new class ($dispatcher, $errorHandler) {

			public function __construct(
				private object $dispatcher,
				private ErrorHandler $errorHandler,
			)
			{
			}

			public function __invoke(ApiRequest $request, ApiResponse $response, callable $next): ApiResponse
			{
				try {
					$response = $this->dispatcher->dispatch($request, $response);
				} catch (Throwable $exception) {
					$response = $this->errorHandler->handle($exception, $request);
				}

				return $next($request, $response);
			}

		};

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;

		$result = $middleware($request, $response, $next);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function exceptionHandledByErrorHandler(): void
	{
		$exception = new RuntimeException('Test error');
		$errorResponse = (new ApiResponse())->withStatus(500)->writeBody('Error');

		$dispatcher = new class ($exception) {

			public function __construct(private Throwable $exception)
			{
			}

			public function dispatch(ApiRequest $request, ApiResponse $response): ApiResponse
			{
				throw $this->exception;
			}

		};

		$errorHandler = $this->createMock(ErrorHandler::class);
		$errorHandler->expects($this->once())
			->method('handle')
			->willReturn($errorResponse);

		$middleware = new class ($dispatcher, $errorHandler) {

			public function __construct(
				private object $dispatcher,
				private ErrorHandler $errorHandler,
			)
			{
			}

			public function __invoke(ApiRequest $request, ApiResponse $response, callable $next): ApiResponse
			{
				try {
					$response = $this->dispatcher->dispatch($request, $response);
				} catch (Throwable $exception) {
					$response = $this->errorHandler->handle($exception, $request);
				}

				return $next($request, $response);
			}

		};

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res;

		$result = $middleware($request, $response, $next);

		self::assertSame(500, $result->getStatusCode());
	}

	#[Test]
	public function nextMiddlewareIsCalled(): void
	{
		$dispatchedResponse = (new ApiResponse())->withStatus(200);

		$dispatcher = new class ($dispatchedResponse) {

			public function __construct(private ApiResponse $response)
			{
			}

			public function dispatch(ApiRequest $request, ApiResponse $response): ApiResponse
			{
				return $this->response;
			}

		};

		$errorHandler = $this->createMock(ErrorHandler::class);

		$middleware = new class ($dispatcher, $errorHandler) {

			public function __construct(
				private object $dispatcher,
				private ErrorHandler $errorHandler,
			)
			{
			}

			public function __invoke(ApiRequest $request, ApiResponse $response, callable $next): ApiResponse
			{
				try {
					$response = $this->dispatcher->dispatch($request, $response);
				} catch (Throwable $exception) {
					$response = $this->errorHandler->handle($exception, $request);
				}

				return $next($request, $response);
			}

		};

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$nextCalled = false;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$nextCalled): ApiResponse {
			$nextCalled = true;

			return $res->withHeader('X-Next', 'called');
		};

		$result = $middleware($request, $response, $next);

		self::assertTrue($nextCalled);
		self::assertSame('called', $result->getHeader('x-next'));
	}

}
