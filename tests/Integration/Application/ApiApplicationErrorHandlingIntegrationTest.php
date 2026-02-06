<?php declare(strict_types = 1);

namespace Tests\Integration\Application;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Throwable;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function str_repeat;

final class ApiApplicationErrorHandlingIntegrationTest extends TestCase
{

	#[Test]
	public function exceptionBubblesWhenCatchExceptionsIsDisabled(): void
	{
		$app = new ApiApplication(
			static fn (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse => throw new RuntimeException(
				'Chain failed',
			),
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Chain failed');

		$app->runWith(new ApiRequest(method: 'GET', uri: '/'));
	}

	#[Test]
	public function chainExceptionIsHandledByErrorHandlerWhenCatchExceptionsIsEnabled(): void
	{
		$errorHandler = new RecordingErrorHandler(
			handleResponse: (new ApiResponse())
				->withStatus(500)
				->writeJsonBody(['source' => 'handle']),
		);

		$app = new ApiApplication(
			static fn (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse => throw new RuntimeException(
				'Chain failed',
			),
			$errorHandler,
		);
		$app->setCatchExceptions(true);

		[$result, $output] = $this->runWithOutput($app, new ApiRequest(method: 'GET', uri: '/fail'));

		self::assertSame(1, $errorHandler->handleCalls);
		self::assertSame(0, $errorHandler->handleFatalCalls);
		self::assertInstanceOf(RuntimeException::class, $errorHandler->lastHandledError);
		self::assertSame('/fail', $errorHandler->lastHandledRequest?->getUri()->getPath());

		self::assertSame(500, $result->getStatusCode());
		self::assertJson($output);
		self::assertStringContainsString('"source":"handle"', $output);
	}

	#[Test]
	public function handleFatalIsUsedWhenErrorHandlerHandleFails(): void
	{
		$handleFailure = new RuntimeException('Error handler failure');
		$errorHandler = new RecordingErrorHandler(
			throwOnHandle: $handleFailure,
			fatalResponse: (new ApiResponse())
				->withStatus(500)
				->writeJsonBody(['source' => 'fatal']),
		);

		$app = new ApiApplication(
			static fn (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse => throw new RuntimeException(
				'Chain failed',
			),
			$errorHandler,
		);
		$app->setCatchExceptions(true);

		[$result, $output] = $this->runWithOutput($app, new ApiRequest(method: 'GET', uri: '/fatal'));

		self::assertSame(1, $errorHandler->handleCalls);
		self::assertSame(1, $errorHandler->handleFatalCalls);
		self::assertSame($handleFailure, $errorHandler->lastFatalError);
		self::assertSame('/fatal', $errorHandler->lastFatalRequest?->getUri()->getPath());

		self::assertSame(500, $result->getStatusCode());
		self::assertJson($output);
		self::assertStringContainsString('"source":"fatal"', $output);
	}

	#[Test]
	public function oversizedRequestUsesEarlyExceptionHandlingPath(): void
	{
		$chainCalled = false;
		$errorHandler = new RecordingErrorHandler(
			handleResponse: (new ApiResponse())
				->withStatus(413)
				->writeJsonBody(['source' => 'early']),
		);

		$app = new ApiApplication(
			static function (ApiRequest $request, ApiResponse $response, callable $next) use (&$chainCalled): ApiResponse {
				$chainCalled = true;

				return $response;
			},
			$errorHandler,
		);
		$app->setCatchExceptions(true);
		$app->setMaxRequestBodySize(10);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/upload',
			body: str_repeat('x', 20),
		);

		[$result, $output] = $this->runWithOutput($app, $request);

		self::assertFalse($chainCalled);
		self::assertSame(1, $errorHandler->handleCalls);
		self::assertSame(0, $errorHandler->handleFatalCalls);
		self::assertInstanceOf(ClientErrorException::class, $errorHandler->lastHandledError);
		self::assertSame(413, $errorHandler->lastHandledError?->getCode());

		self::assertSame(413, $result->getStatusCode());
		self::assertJson($output);
		self::assertStringContainsString('"source":"early"', $output);
	}

	/**
	 * @return array{ApiResponse, string}
	 */
	private function runWithOutput(ApiApplication $app, ApiRequest $request): array
	{
		ob_start();
		$response = $app->runWith($request);
		$output = ob_get_clean();

		return [$response, is_string($output) ? $output : ''];
	}

}

final class RecordingErrorHandler implements ErrorHandler
{

	public int $handleCalls = 0;

	public int $handleFatalCalls = 0;

	public Throwable|null $lastHandledError = null;

	public ApiRequest|null $lastHandledRequest = null;

	public Throwable|null $lastFatalError = null;

	public ApiRequest|null $lastFatalRequest = null;

	private bool $debugMode = false;

	public function __construct(
		private ApiResponse|null $handleResponse = null,
		private ApiResponse|null $fatalResponse = null,
		private Throwable|null $throwOnHandle = null,
	)
	{
	}

	public function handle(Throwable $error, ApiRequest $request): ApiResponse
	{
		$this->handleCalls++;
		$this->lastHandledError = $error;
		$this->lastHandledRequest = $request;

		if ($this->throwOnHandle !== null) {
			throw $this->throwOnHandle;
		}

		return $this->handleResponse ?? (new ApiResponse())->withStatus(500)->writeJsonBody(
			['source' => 'handle-default'],
		);
	}

	public function handleFatal(Throwable $error, ApiRequest $request): ApiResponse
	{
		$this->handleFatalCalls++;
		$this->lastFatalError = $error;
		$this->lastFatalRequest = $request;

		return $this->fatalResponse ?? (new ApiResponse())->withStatus(500)->writeJsonBody(
			['source' => 'fatal-default'],
		);
	}

	public function setDebugMode(bool $debugMode): void
	{
		$this->debugMode = $debugMode;
	}

}
