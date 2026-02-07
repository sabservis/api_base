<?php declare(strict_types = 1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\BasicAuthMiddleware;
use function base64_encode;
use function password_hash;
use const PASSWORD_DEFAULT;

final class BasicAuthMiddlewareTest extends TestCase
{

	private function createBasicAuthHeader(string $username, string $password): string
	{
		return 'Basic ' . base64_encode($username . ':' . $password);
	}

	private function createNext(): callable
	{
		return static fn (ApiRequest $req, ApiResponse $res): ApiResponse => $res->withStatus(200);
	}

	#[Test]
	public function unauthorizedWithoutHeader(): void
	{
		$middleware = new BasicAuthMiddleware('Test Zone');
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(401, $result->getStatusCode());
		self::assertStringContainsString('Test Zone', $result->getHeader('www-authenticate'));
	}

	#[Test]
	public function unauthorizedWithInvalidCredentials(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('admin', 'wrong')],
		);
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(401, $result->getStatusCode());
	}

	#[Test]
	public function unauthorizedWithNonExistentUser(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('unknown', 'secret')],
		);
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(401, $result->getStatusCode());
	}

	#[Test]
	public function authorizedWithValidCredentials(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('admin', 'secret')],
		);
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function authorizedWithUnsecuredPassword(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('api', 'plain-password', unsecured: true);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('api', 'plain-password')],
		);
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function usernameAttributeSetOnSuccess(): void
	{
		$capturedRequest = null;
		$next = static function (ApiRequest $req, ApiResponse $res) use (&$capturedRequest): ApiResponse {
			$capturedRequest = $req;

			return $res->withStatus(200);
		};

		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('john', password_hash('password', PASSWORD_DEFAULT));

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('john', 'password')],
		);
		$response = new ApiResponse();

		$middleware($request, $response, $next);

		self::assertSame('john', $capturedRequest->getAttribute('username'));
	}

	#[Test]
	public function multipleUsers(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('admin', password_hash('admin-pass', PASSWORD_DEFAULT));
		$middleware->addUser('user', password_hash('user-pass', PASSWORD_DEFAULT));

		// Test admin
		$request1 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('admin', 'admin-pass')],
		);
		$result1 = $middleware($request1, new ApiResponse(), $this->createNext());
		self::assertSame(200, $result1->getStatusCode());

		// Test user
		$request2 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('user', 'user-pass')],
		);
		$result2 = $middleware($request2, new ApiResponse(), $this->createNext());
		self::assertSame(200, $result2->getStatusCode());

		// Test cross - user with admin password should fail
		$request3 = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => $this->createBasicAuthHeader('user', 'admin-pass')],
		);
		$result3 = $middleware($request3, new ApiResponse(), $this->createNext());
		self::assertSame(401, $result3->getStatusCode());
	}

	#[Test]
	public function invalidAuthorizationHeaderFormat(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		// Not Basic auth
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => 'Bearer some-token'],
		);
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(401, $result->getStatusCode());
	}

	#[Test]
	public function malformedBasicAuthHeader(): void
	{
		$middleware = new BasicAuthMiddleware();
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		// Malformed base64 without colon separator
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['authorization' => 'Basic ' . base64_encode('nocolon')],
		);
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertSame(401, $result->getStatusCode());
	}

	#[Test]
	public function fluentInterface(): void
	{
		$middleware = new BasicAuthMiddleware();

		$result = $middleware
			->addUser('user1', 'pass1', true)
			->addUser('user2', 'pass2', true);

		self::assertSame($middleware, $result);
	}

	#[Test]
	public function customRealmTitle(): void
	{
		$middleware = new BasicAuthMiddleware('Admin Area');
		$middleware->addUser('admin', password_hash('secret', PASSWORD_DEFAULT));

		$request = new ApiRequest(method: 'GET', uri: '/');
		$response = new ApiResponse();

		$result = $middleware($request, $response, $this->createNext());

		self::assertStringContainsString('Admin Area', $result->getHeader('www-authenticate'));
	}

}
