<?php declare(strict_types = 1);

namespace Tests\Unit\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\ErrorHandler\ErrorResponseBuilder;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ServerErrorException;

final class ErrorResponseBuilderTest extends TestCase
{

	private ErrorResponseBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new ErrorResponseBuilder();
	}

	// === build() tests ===

	#[Test]
	public function buildClientException(): void
	{
		$exception = new ClientErrorException('Not found', 404);

		$response = $this->builder->build($exception);

		self::assertSame(404, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeader('content-type'));

		$body = json_decode($response->getBody(), true);
		self::assertSame(404, $body['code']);
		self::assertSame('Not found', $body['message']);
	}

	#[Test]
	public function buildServerException(): void
	{
		$exception = new ServerErrorException('Database error', 500);

		$response = $this->builder->build($exception);

		self::assertSame(500, $response->getStatusCode());

		$body = json_decode($response->getBody(), true);
		self::assertSame(500, $body['code']);
		self::assertSame('Database error', $body['message']);
	}

	#[Test]
	public function buildGenericExceptionHidesMessage(): void
	{
		$exception = new RuntimeException('Secret error details');

		$response = $this->builder->build($exception, includeTrace: false);

		self::assertSame(500, $response->getStatusCode());

		$body = json_decode($response->getBody(), true);
		self::assertSame(500, $body['code']);
		self::assertSame(ServerErrorException::$defaultMessage, $body['message']);
	}

	#[Test]
	public function buildGenericExceptionShowsMessageInDebug(): void
	{
		$exception = new RuntimeException('Secret error details');

		$response = $this->builder->build($exception, includeTrace: true);

		$body = json_decode($response->getBody(), true);
		self::assertSame('Secret error details', $body['message']);
	}

	#[Test]
	public function buildWithContextFromApiException(): void
	{
		$exception = (new ClientErrorException('Validation failed', 422))
			->withContext(['field' => 'email', 'error' => 'invalid']);

		$response = $this->builder->build($exception);

		$body = json_decode($response->getBody(), true);
		self::assertSame(['field' => 'email', 'error' => 'invalid'], $body['context']);
	}

	#[Test]
	public function buildWithContextPreservedInDebugMode(): void
	{
		$exception = (new ClientErrorException('Validation failed', 422))
			->withContext(['field' => 'email']);

		$response = $this->builder->build($exception, includeTrace: true);

		$body = json_decode($response->getBody(), true);
		// ApiException context should be preserved, not overwritten by stack trace
		self::assertSame(['field' => 'email'], $body['context']);
	}

	#[Test]
	public function buildIncludesStackTraceInDebugModeForNonApiException(): void
	{
		$exception = new RuntimeException('Error');

		$response = $this->builder->build($exception, includeTrace: true);

		$body = json_decode($response->getBody(), true);
		self::assertArrayHasKey('context', $body);
		self::assertIsArray($body['context']);
		// Stack trace should have file/function entries
		self::assertArrayHasKey('file', $body['context'][0] ?? []);
	}

	#[Test]
	public function buildSanitizesStackTraceRemovesArguments(): void
	{
		// Create exception with arguments that would contain sensitive data
		$exception = $this->createExceptionWithSensitiveArgs('secret_password', 'api_token_123');

		$response = $this->builder->build($exception, includeTrace: true);

		$body = json_decode($response->getBody(), true);
		self::assertArrayHasKey('context', $body);

		// Verify stack trace does NOT contain 'args' key (which would leak sensitive data)
		foreach ($body['context'] as $frame) {
			self::assertArrayNotHasKey('args', $frame, 'Stack trace must not contain function arguments');
		}

		// Verify it still contains useful debug info
		$firstFrame = $body['context'][0] ?? [];
		self::assertArrayHasKey('function', $firstFrame);
	}

	#[Test]
	public function buildSanitizedTraceContainsOnlySafeKeys(): void
	{
		$exception = new RuntimeException('Error');

		$response = $this->builder->build($exception, includeTrace: true);

		$body = json_decode($response->getBody(), true);
		$allowedKeys = ['file', 'line', 'function', 'class', 'type'];

		foreach ($body['context'] as $frame) {
			foreach (array_keys($frame) as $key) {
				self::assertContains(
					$key,
					$allowedKeys,
					"Stack trace frame contains unexpected key: {$key}",
				);
			}
		}
	}

	private function createExceptionWithSensitiveArgs(string $password, string $token): RuntimeException
	{
		// This method intentionally has sensitive-looking parameters
		// to test that they don't appear in the sanitized trace
		return new RuntimeException('Error with sensitive context');
	}

	#[Test]
	public function buildNonApiExceptionDefaults500(): void
	{
		$exception = new RuntimeException('Error', 999);

		$response = $this->builder->build($exception);

		self::assertSame(500, $response->getStatusCode());
	}

	// === buildFatal() tests ===

	#[Test]
	public function buildFatalReturns500(): void
	{
		$exception = new RuntimeException('Fatal');

		$response = $this->builder->buildFatal($exception, debugMode: false);

		self::assertSame(500, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeader('content-type'));

		$body = json_decode($response->getBody(), true);
		self::assertSame(500, $body['code']);
		self::assertSame(ServerErrorException::$defaultMessage, $body['message']);
		self::assertSame([], $body['context']);
	}

	#[Test]
	public function buildFatalShowsDetailsInDebugMode(): void
	{
		$exception = new RuntimeException('Fatal details');

		$response = $this->builder->buildFatal($exception, debugMode: true);

		$body = json_decode($response->getBody(), true);
		self::assertSame(500, $body['code']);
		self::assertStringContainsString('Fatal details', $body['message']);
		self::assertArrayHasKey('file', $body['context']);
		self::assertArrayHasKey('line', $body['context']);
	}

	// === Consistent format tests ===

	#[Test]
	public function responseFormatIsConsistent(): void
	{
		$exception = new ClientErrorException('Error', 400);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		// Keys should be in consistent order: code, message, context (if present)
		$keys = array_keys($body);
		self::assertSame('code', $keys[0]);
		self::assertSame('message', $keys[1]);
	}

	// === Context Sanitization Tests ===

	#[Test]
	public function sanitizesPasswordFromContext(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'field' => 'email',
				'password' => 'secret123',
				'user_password' => 'also_secret',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('field', $body['context']);
		self::assertArrayNotHasKey('password', $body['context']);
		self::assertArrayNotHasKey('user_password', $body['context']);
	}

	#[Test]
	public function sanitizesTokenFromContext(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'user_id' => 123,
				'token' => 'abc123',
				'access_token' => 'xyz789',
				'api_token' => 'secret',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('user_id', $body['context']);
		self::assertArrayNotHasKey('token', $body['context']);
		self::assertArrayNotHasKey('access_token', $body['context']);
		self::assertArrayNotHasKey('api_token', $body['context']);
	}

	#[Test]
	public function sanitizesApiKeyFromContext(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'endpoint' => '/users',
				'api_key' => 'sk_live_xxx',
				'apiKey' => 'pk_test_xxx',
				'api-key' => 'another_key',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('endpoint', $body['context']);
		self::assertArrayNotHasKey('api_key', $body['context']);
		self::assertArrayNotHasKey('apiKey', $body['context']);
		self::assertArrayNotHasKey('api-key', $body['context']);
	}

	#[Test]
	public function sanitizesSecretFromContext(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'name' => 'test',
				'secret' => 'shh',
				'client_secret' => 'very_secret',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('name', $body['context']);
		self::assertArrayNotHasKey('secret', $body['context']);
		self::assertArrayNotHasKey('client_secret', $body['context']);
	}

	#[Test]
	public function sanitizesAuthFromContext(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'method' => 'POST',
				'auth' => 'Bearer xxx',
				'authorization' => 'Basic abc',
				'auth_header' => 'secret',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('method', $body['context']);
		self::assertArrayNotHasKey('auth', $body['context']);
		self::assertArrayNotHasKey('authorization', $body['context']);
		self::assertArrayNotHasKey('auth_header', $body['context']);
	}

	#[Test]
	public function sanitizesNestedContext(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'user' => [
					'id' => 1,
					'email' => 'test@example.com',
					'password_hash' => 'bcrypt_hash',
					'credentials' => [
						'api_key' => 'secret',
					],
				],
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('user', $body['context']);
		self::assertArrayHasKey('id', $body['context']['user']);
		self::assertArrayHasKey('email', $body['context']['user']);
		self::assertArrayNotHasKey('password_hash', $body['context']['user']);
		self::assertArrayNotHasKey('credentials', $body['context']['user']);
	}

	#[Test]
	public function sanitizesObjectsInContext(): void
	{
		$user = new \stdClass();
		$user->id = 1;
		$user->password = 'secret';

		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'user' => $user,
				'field' => 'email',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('field', $body['context']);
		// Object should be converted to safe string representation
		self::assertStringContainsString('stdClass', $body['context']['user']);
	}

	#[Test]
	public function customContextFilterIsApplied(): void
	{
		$this->builder->setContextFilter(function (array $context): array {
			// Custom filter: remove 'internal_code' key
			unset($context['internal_code']);

			return $context;
		});

		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'field' => 'email',
				'internal_code' => 'ERR_001',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('field', $body['context']);
		self::assertArrayNotHasKey('internal_code', $body['context']);
	}

	#[Test]
	public function customFilterIsAppliedAfterDefaultSanitization(): void
	{
		$filterCalled = false;
		$receivedContext = null;

		$this->builder->setContextFilter(function (array $context) use (&$filterCalled, &$receivedContext): array {
			$filterCalled = true;
			$receivedContext = $context;

			return $context;
		});

		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'field' => 'email',
				'password' => 'secret', // Should be removed BEFORE custom filter
			]);

		$this->builder->build($exception);

		self::assertTrue($filterCalled);
		// Custom filter should NOT receive the password key (already filtered)
		self::assertArrayNotHasKey('password', $receivedContext);
	}

	#[Test]
	public function disableContextRemovesAllContext(): void
	{
		$this->builder->disableContext();

		$exception = (new ClientErrorException('Error', 400))
			->withContext(['field' => 'email']);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayNotHasKey('context', $body);
	}

	#[Test]
	public function enableContextRestoresContext(): void
	{
		$this->builder->disableContext();
		$this->builder->enableContext();

		$exception = (new ClientErrorException('Error', 400))
			->withContext(['field' => 'email']);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('context', $body);
		self::assertSame(['field' => 'email'], $body['context']);
	}

	#[Test]
	public function disableContextAlsoAffectsDebugTrace(): void
	{
		$this->builder->disableContext();

		$exception = new RuntimeException('Error');

		$response = $this->builder->build($exception, includeTrace: true);
		$body = json_decode($response->getBody(), true);

		// Even in debug mode with trace, context should be absent
		self::assertArrayNotHasKey('context', $body);
	}

	#[Test]
	public function preservesSafeContextKeys(): void
	{
		$exception = (new ClientErrorException('Validation failed', 422))
			->withContext([
				'field' => 'email',
				'value' => 'invalid-email',
				'expected' => 'valid email format',
				'constraint' => 'Email',
				'path' => 'user.email',
				'index' => 0,
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		// All these safe keys should be preserved
		self::assertSame('email', $body['context']['field']);
		self::assertSame('invalid-email', $body['context']['value']);
		self::assertSame('valid email format', $body['context']['expected']);
		self::assertSame('Email', $body['context']['constraint']);
		self::assertSame('user.email', $body['context']['path']);
		self::assertSame(0, $body['context']['index']);
	}

	// === Trace ID Provider Tests ===

	#[Test]
	public function buildWithTraceIdProvider(): void
	{
		$this->builder->setTraceIdProvider(fn () => 'trace-abc-123');

		$exception = new ClientErrorException('Not found', 404);
		$response = $this->builder->build($exception);

		$body = json_decode($response->getBody(), true);
		self::assertSame('trace-abc-123', $body['traceId']);
	}

	#[Test]
	public function buildWithTraceIdProviderReturningNull(): void
	{
		$this->builder->setTraceIdProvider(fn () => null);

		$exception = new ClientErrorException('Not found', 404);
		$response = $this->builder->build($exception);

		$body = json_decode($response->getBody(), true);
		self::assertArrayNotHasKey('traceId', $body);
	}

	#[Test]
	public function buildFatalWithTraceIdProvider(): void
	{
		$this->builder->setTraceIdProvider(fn () => 'fatal-trace-456');

		$exception = new RuntimeException('Fatal');
		$response = $this->builder->buildFatal($exception, debugMode: false);

		$body = json_decode($response->getBody(), true);
		self::assertSame('fatal-trace-456', $body['traceId']);
	}

	#[Test]
	public function buildFatalWithTraceIdProviderReturningNull(): void
	{
		$this->builder->setTraceIdProvider(fn () => null);

		$exception = new RuntimeException('Fatal');
		$response = $this->builder->buildFatal($exception, debugMode: false);

		$body = json_decode($response->getBody(), true);
		self::assertArrayNotHasKey('traceId', $body);
	}

	#[Test]
	public function traceIdAppearsBeforeContext(): void
	{
		$this->builder->setTraceIdProvider(fn () => 'trace-order-test');

		$exception = (new ClientErrorException('Error', 400))
			->withContext(['field' => 'email']);
		$response = $this->builder->build($exception);

		$body = json_decode($response->getBody(), true);
		$keys = array_keys($body);
		self::assertSame('code', $keys[0]);
		self::assertSame('message', $keys[1]);
		self::assertSame('traceId', $keys[2]);
		self::assertSame('context', $keys[3]);
	}

	#[Test]
	public function caseInsensitiveSensitiveKeyMatching(): void
	{
		$exception = (new ClientErrorException('Error', 400))
			->withContext([
				'field' => 'test',
				'PASSWORD' => 'secret',
				'Token' => 'abc',
				'API_KEY' => 'xyz',
				'Secret' => 'shh',
			]);

		$response = $this->builder->build($exception);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('field', $body['context']);
		self::assertArrayNotHasKey('PASSWORD', $body['context']);
		self::assertArrayNotHasKey('Token', $body['context']);
		self::assertArrayNotHasKey('API_KEY', $body['context']);
		self::assertArrayNotHasKey('Secret', $body['context']);
	}

}
