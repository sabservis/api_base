<?php declare(strict_types = 1);

namespace Tests\Unit\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\Runtime\SnapshotException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function json_decode;

final class SimpleErrorHandlerTest extends TestCase
{

	#[Test]
	public function handleClientException(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(true);

		$exception = new ClientErrorException('Not found', 404);
		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handle($exception, $request);

		self::assertSame(404, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeader('content-type'));

		$body = json_decode($response->getBody(), true);
		self::assertSame('Not found', $body['message']);
		self::assertSame(404, $body['code']);
	}

	#[Test]
	public function handleServerException(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(true);

		$exception = new ServerErrorException('Internal error', 500);
		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handle($exception, $request);

		self::assertSame(500, $response->getStatusCode());

		$body = json_decode($response->getBody(), true);
		self::assertSame('Internal error', $body['message']);
	}

	#[Test]
	public function handleGenericException(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(true);

		$exception = new RuntimeException('Something went wrong', 123);
		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handle($exception, $request);

		self::assertSame(500, $response->getStatusCode());

		$body = json_decode($response->getBody(), true);
		self::assertSame('Something went wrong', $body['message']);
	}

	#[Test]
	public function handleSnapshotException(): void
	{
		$handler = new SimpleErrorHandler();

		$originalResponse = (new ApiResponse())
			->withStatus(422)
			->writeBody('Validation error');

		$originalException = new RuntimeException('Original error');
		$request = new ApiRequest(method: 'GET', uri: '/');
		$exception = new SnapshotException($originalException, $request, $originalResponse);

		$response = $handler->handle($exception, $request);

		self::assertSame(422, $response->getStatusCode());
		self::assertSame('Validation error', $response->getBody());
	}

	#[Test]
	public function handleWithContext(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(true);

		$exception = (new ClientErrorException('Validation failed', 422))
			->withContext(['field' => 'email', 'error' => 'invalid']);

		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handle($exception, $request);

		$body = json_decode($response->getBody(), true);
		self::assertSame(['field' => 'email', 'error' => 'invalid'], $body['context']);
	}

	#[Test]
	public function debugModeShowsStackTrace(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(true);

		$exception = new RuntimeException('Debug error');
		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handle($exception, $request);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('context', $body);
		self::assertIsArray($body['context']);
	}

	#[Test]
	public function handleFatalReturns500(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(false);

		$exception = new RuntimeException('Fatal error in handler');
		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handleFatal($exception, $request);

		self::assertSame(500, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeader('content-type'));

		$body = json_decode($response->getBody(), true);
		self::assertSame(500, $body['code']);
		self::assertSame(ServerErrorException::$defaultMessage, $body['message']);
		self::assertSame([], $body['context']);
	}

	#[Test]
	public function handleFatalInDebugModeShowsDetails(): void
	{
		$handler = new SimpleErrorHandler();
		$handler->setDebugMode(true);

		$exception = new RuntimeException('Fatal error details');
		$request = new ApiRequest(method: 'GET', uri: '/');

		$response = $handler->handleFatal($exception, $request);

		self::assertSame(500, $response->getStatusCode());

		$body = json_decode($response->getBody(), true);
		self::assertSame(500, $body['code']);
		self::assertStringContainsString('Fatal error details', $body['message']);
		self::assertArrayHasKey('file', $body['context']);
		self::assertArrayHasKey('line', $body['context']);
	}

}
