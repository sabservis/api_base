<?php declare(strict_types = 1);

namespace Tests\Unit\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Sabservis\Api\ErrorHandler\PsrLogErrorHandler;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\Runtime\SnapshotException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function json_decode;

final class PsrLogErrorHandlerTest extends TestCase
{

	private function createRequest(): ApiRequest
	{
		return new ApiRequest(method: 'GET', uri: '/test');
	}

	#[Test]
	public function logsNonApiException(): void
	{
		$exception = new RuntimeException('Database connection failed');
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				'Database connection failed',
				$this->callback(static fn (array $ctx): bool => $ctx['exception'] === $exception),
			);

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true); // Avoid Tracy logging
		$handler->handle($exception, $request);
	}

	#[Test]
	public function doesNotLogApiException(): void
	{
		$exception = new ClientErrorException('Invalid input', 400);
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('error');

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true); // Avoid Tracy logging
		$handler->handle($exception, $request);
	}

	#[Test]
	public function logsServerErrorPreviousAtErrorLevel(): void
	{
		$previous = new RuntimeException('Original database error');
		$exception = new ServerErrorException('Database error', 500, $previous);
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('log')
			->with(
				LogLevel::ERROR,
				'Original database error',
				$this->callback(static fn (array $ctx): bool => $ctx['exception'] === $previous),
			);

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true); // Avoid Tracy logging
		$handler->handle($exception, $request);
	}

	#[Test]
	public function logsClientErrorPreviousAtDebugLevel(): void
	{
		$previous = new RuntimeException('Validation detail');
		$exception = new ClientErrorException('Invalid data', 400, $previous);
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('log')
			->with(
				LogLevel::DEBUG,
				'Validation detail',
				$this->callback(static fn (array $ctx): bool => $ctx['exception'] === $previous),
			);

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true); // Avoid Tracy logging
		$handler->handle($exception, $request);
	}

	#[Test]
	public function doesNotLogApiExceptionWithoutPrevious(): void
	{
		$exception = new ClientErrorException('Not found', 404);
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('error');
		$logger->expects($this->never())->method('log');

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true); // Avoid Tracy logging
		$handler->handle($exception, $request);
	}

	#[Test]
	public function returnsApiResponse(): void
	{
		$exception = new ClientErrorException('Bad request', 400);
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true); // Avoid Tracy logging
		$response = $handler->handle($exception, $request);

		self::assertInstanceOf(ApiResponse::class, $response);
		self::assertSame(400, $response->getStatusCode());
	}

	#[Test]
	public function unwrapsSnapshotException(): void
	{
		$original = new RuntimeException('Real error');
		$request = $this->createRequest();
		$response = new ApiResponse();
		$snapshot = new SnapshotException($original, $request, $response);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				'Real error',
				$this->callback(static fn (array $ctx): bool => $ctx['exception'] === $original),
			);

		$handler = new PsrLogErrorHandler($logger);
		$handler->handle($snapshot, $request);
	}

	#[Test]
	public function returnsSnapshotResponse(): void
	{
		$original = new RuntimeException('Error');
		$request = $this->createRequest();
		$response = (new ApiResponse())->withStatus(503)->writeBody('Snapshot response');
		$snapshot = new SnapshotException($original, $request, $response);

		$logger = $this->createMock(LoggerInterface::class);

		$handler = new PsrLogErrorHandler($logger);
		$result = $handler->handle($snapshot, $request);

		self::assertSame(503, $result->getStatusCode());
		self::assertSame('Snapshot response', $result->getBody());
	}

	#[Test]
	public function inheritsFromSimpleErrorHandler(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$handler = new PsrLogErrorHandler($logger);

		self::assertInstanceOf(SimpleErrorHandler::class, $handler);
	}

	#[Test]
	public function setDebugModeWorks(): void
	{
		$exception = new RuntimeException('Test error');
		$request = $this->createRequest();

		$logger = $this->createMock(LoggerInterface::class);

		$handler = new PsrLogErrorHandler($logger);
		$handler->setDebugMode(true);

		$response = $handler->handle($exception, $request);
		$body = json_decode($response->getBody(), true);

		// In debug mode, trace should be in context
		self::assertArrayHasKey('context', $body);
		self::assertNotEmpty($body['context']);
	}

}
