<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\AuditLogMiddleware;
use stdClass;
use function strlen;

final class AuditLogMiddlewareTest extends TestCase
{

	#[Test]
	public function addsRequestIdHeader(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$middleware = new AuditLogMiddleware($logger);

		$request = new ApiRequest(method: 'GET', uri: '/api/users');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result = $middleware($request, $response, $next);

		self::assertNotNull($result->getHeader('x-request-id'));
		self::assertSame(32, strlen($result->getHeader('x-request-id'))); // 16 bytes hex = 32 chars
	}

	#[Test]
	public function logsSuccessfulRequest(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => isset($context['request_id'])
						&& $context['method'] === 'GET'
						&& $context['path'] === '/api/users'
						&& $context['status'] === 200
						&& isset($context['duration_ms'])),
			);

		$middleware = new AuditLogMiddleware($logger);

		$request = new ApiRequest(method: 'GET', uri: '/api/users');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function logsErrorOnException(): void
	{
		$exception = new RuntimeException('Test error');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				'API Error',
				$this->callback(static fn (array $context): bool => isset($context['request_id'])
						&& $context['method'] === 'POST'
						&& $context['error_class'] === RuntimeException::class
						&& $context['error_message'] === 'Test error'),
			);

		$middleware = new AuditLogMiddleware($logger);

		$request = new ApiRequest(method: 'POST', uri: '/api/users');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => throw $exception;

		$this->expectException(RuntimeException::class);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function rethrowsException(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$middleware = new AuditLogMiddleware($logger);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => throw new RuntimeException(
			'Original error',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Original error');

		$middleware($request, $response, $next);
	}

	#[Test]
	public function setsRequestIdAttribute(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$middleware = new AuditLogMiddleware($logger);

		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertNotNull($capturedRequest->getAttribute('request_id'));
	}

	#[Test]
	public function logsClientIpAndUserAgent(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['ip'] === '192.168.1.1'
						&& $context['user_agent'] === 'TestClient/1.0'),
			);

		$middleware = new AuditLogMiddleware($logger);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['user-agent' => 'TestClient/1.0'],
			serverParams: ['REMOTE_ADDR' => '192.168.1.1'],
		);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function logsUserId(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['user_id'] === 42),
			);

		$middleware = new AuditLogMiddleware($logger);

		$user = new stdClass();
		$user->id = 42;

		$request = (new ApiRequest(method: 'GET', uri: '/'))
			->withAttribute('user', $user);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function uniqueRequestIdsPerRequest(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$middleware = new AuditLogMiddleware($logger);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$result1 = $middleware($request, $response, $next);
		$result2 = $middleware($request, $response, $next);

		self::assertNotSame(
			$result1->getHeader('x-request-id'),
			$result2->getHeader('x-request-id'),
		);
	}

	#[Test]
	public function handlesNullUserAttribute(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['user_id'] === null),
			);

		$middleware = new AuditLogMiddleware($logger);

		// No user attribute set (null)
		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function handlesStringUserAttribute(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['user_id'] === null),
			);

		$middleware = new AuditLogMiddleware($logger);

		// User attribute is string (not object)
		$request = (new ApiRequest(method: 'GET', uri: '/'))
			->withAttribute('user', 'some-string-value');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function handlesArrayUserAttribute(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['user_id'] === null),
			);

		$middleware = new AuditLogMiddleware($logger);

		// User attribute is array (not object)
		$request = (new ApiRequest(method: 'GET', uri: '/'))
			->withAttribute('user', ['id' => 42, 'name' => 'test']);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function handlesUserObjectWithoutIdProperty(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['user_id'] === null),
			);

		$middleware = new AuditLogMiddleware($logger);

		// User object without id property
		$user = new stdClass();
		$user->name = 'test';

		$request = (new ApiRequest(method: 'GET', uri: '/'))
			->withAttribute('user', $user);
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function handlesNullUserInErrorLog(): void
	{
		$exception = new RuntimeException('Test error');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				'API Error',
				$this->callback(static fn (array $context): bool => $context['user_id'] === null),
			);

		$middleware = new AuditLogMiddleware($logger);

		// No user attribute
		$request = new ApiRequest(method: 'POST', uri: '/');
		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => throw $exception;

		$this->expectException(RuntimeException::class);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function logsRealClientIpBehindTrustedProxy(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['ip'] === '203.0.113.50'),
			);

		$middleware = new AuditLogMiddleware($logger);

		// Request from behind reverse proxy
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '203.0.113.50'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'], // Proxy IP
		);
		// Configure trusted proxy
		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

	#[Test]
	public function logsRemoteAddrWhenNoTrustedProxies(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with(
				'API Request',
				$this->callback(static fn (array $context): bool => $context['ip'] === '10.0.0.1'),
			);

		$middleware = new AuditLogMiddleware($logger);

		// Attacker tries to spoof IP, but no trusted proxies configured
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '203.0.113.50'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);
		// No trusted proxies - X-Forwarded-For should be ignored

		$response = new ApiResponse();
		$next = static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);

		$middleware($request, $response, $next);
	}

}
