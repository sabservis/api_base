<?php declare(strict_types = 1);

namespace Tests\Integration\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\BasicAuthMiddleware;
use Sabservis\Api\Middleware\CORSMiddleware;
use Sabservis\Api\Middleware\RateLimitMiddleware;
use Sabservis\Api\Middleware\RequestSizeLimitMiddleware;
use Sabservis\Api\Utils\ChainBuilder;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use function base64_encode;
use function password_hash;
use const PASSWORD_DEFAULT;

final class MiddlewarePipelineIntegrationTest extends TestCase
{

	#[Test]
	public function preflightRequestBypassesAuthRateLimitAndRequestSizeChecks(): void
	{
		$endpointCalls = 0;
		$chain = $this->createPipeline(
			maxBodySize: 10,
			maxRequests: 1,
			endpointCalls: $endpointCalls,
		);

		$request = new ApiRequest(
			method: 'OPTIONS',
			uri: '/secure',
			headers: [
				'Origin' => 'https://app.example.com',
				'Content-Length' => '9999',
			],
			body: 'this-body-exceeds-limit',
			serverParams: ['REMOTE_ADDR' => '203.0.113.10'],
		);

		$response = $chain($request, new ApiResponse());

		self::assertSame(204, $response->getStatusCode());
		self::assertSame('https://app.example.com', $response->getHeader('access-control-allow-origin'));
		self::assertNull($response->getHeader('www-authenticate'));
		self::assertNull($response->getHeader('x-ratelimit-limit'));
		self::assertSame(0, $endpointCalls);
	}

	#[Test]
	public function unauthorizedRequestGetsCorsHeadersAndDoesNotConsumeRateLimit(): void
	{
		$endpointCalls = 0;
		$chain = $this->createPipeline(
			maxBodySize: 1_024,
			maxRequests: 1,
			endpointCalls: $endpointCalls,
		);

		$unauthorizedRequest = new ApiRequest(
			method: 'GET',
			uri: '/secure',
			headers: ['Origin' => 'https://app.example.com'],
			serverParams: ['REMOTE_ADDR' => '203.0.113.10'],
		);

		$unauthorizedResponse = $chain($unauthorizedRequest, new ApiResponse());

		self::assertSame(401, $unauthorizedResponse->getStatusCode());
		self::assertNotNull($unauthorizedResponse->getHeader('www-authenticate'));
		self::assertSame('https://app.example.com', $unauthorizedResponse->getHeader('access-control-allow-origin'));
		self::assertNull($unauthorizedResponse->getHeader('x-ratelimit-limit'));

		$authorizedRequest = new ApiRequest(
			method: 'GET',
			uri: '/secure',
			headers: [
				'Origin' => 'https://app.example.com',
				'Authorization' => $this->createBasicAuthHeader('api', 'secret'),
			],
			serverParams: ['REMOTE_ADDR' => '203.0.113.10'],
		);

		$authorizedResponse = $chain($authorizedRequest, new ApiResponse());

		self::assertSame(200, $authorizedResponse->getStatusCode());
		self::assertSame('1', $authorizedResponse->getHeader('x-ratelimit-limit'));
		self::assertSame('0', $authorizedResponse->getHeader('x-ratelimit-remaining'));
		self::assertSame(1, $endpointCalls);
	}

	#[Test]
	public function oversizedRequestIsRejectedBeforeAuthAndRateLimit(): void
	{
		$endpointCalls = 0;
		$chain = $this->createPipeline(
			maxBodySize: 16,
			maxRequests: 1,
			endpointCalls: $endpointCalls,
		);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/secure',
			headers: [
				'Origin' => 'https://app.example.com',
				'Authorization' => $this->createBasicAuthHeader('api', 'secret'),
				'Content-Length' => '1024',
			],
			body: 'ok',
			serverParams: ['REMOTE_ADDR' => '203.0.113.10'],
		);

		try {
			$chain($request, new ApiResponse());
			self::fail('Expected ClientErrorException to be thrown');
		} catch (ClientErrorException $e) {
			self::assertSame(413, $e->getCode());
			self::assertStringContainsString('Payload Too Large', $e->getMessage());
		}

		self::assertSame(0, $endpointCalls);
	}

	#[Test]
	public function secondAuthorizedRequestHitsRateLimit(): void
	{
		$endpointCalls = 0;
		$chain = $this->createPipeline(
			maxBodySize: 1_024,
			maxRequests: 1,
			endpointCalls: $endpointCalls,
		);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/secure',
			headers: [
				'Origin' => 'https://app.example.com',
				'Authorization' => $this->createBasicAuthHeader('api', 'secret'),
			],
			serverParams: ['REMOTE_ADDR' => '203.0.113.10'],
		);

		$firstResponse = $chain($request, new ApiResponse());

		self::assertSame(200, $firstResponse->getStatusCode());
		self::assertSame('1', $firstResponse->getHeader('x-ratelimit-limit'));
		self::assertSame('0', $firstResponse->getHeader('x-ratelimit-remaining'));

		try {
			$chain($request, new ApiResponse());
			self::fail('Expected ClientErrorException to be thrown');
		} catch (ClientErrorException $e) {
			self::assertSame(429, $e->getCode());
			self::assertStringContainsString('Too Many Requests', $e->getMessage());
		}

		self::assertSame(1, $endpointCalls);
	}

	private function createBasicAuthHeader(string $username, string $password): string
	{
		return 'Basic ' . base64_encode($username . ':' . $password);
	}

	private function createPipeline(int $maxBodySize, int $maxRequests, int &$endpointCalls): callable
	{
		$basicAuth = new BasicAuthMiddleware('Integration Test Zone');
		$basicAuth->addUser('api', password_hash('secret', PASSWORD_DEFAULT));

		$chainBuilder = new ChainBuilder();
		$chainBuilder->add(new CORSMiddleware(
			allowedOrigins: ['https://app.example.com'],
			allowedMethods: ['GET', 'POST', 'OPTIONS'],
			allowedHeaders: ['Content-Type', 'Authorization'],
		));
		$chainBuilder->add(new RequestSizeLimitMiddleware($maxBodySize));
		$chainBuilder->add($basicAuth);
		$chainBuilder->add(new RateLimitMiddleware(
			cache: new ArrayAdapter(),
			maxRequests: $maxRequests,
			windowSeconds: 60,
		));
		$chainBuilder->add(
			static function (ApiRequest $request, ApiResponse $response, callable $next) use (&$endpointCalls): ApiResponse {
				$endpointCalls++;

				return $response
					->withStatus(200)
					->writeJsonBody([
						'ok' => true,
						'username' => $request->getAttribute('username'),
					]);
			},
		);

		return $chainBuilder->create();
	}

}
