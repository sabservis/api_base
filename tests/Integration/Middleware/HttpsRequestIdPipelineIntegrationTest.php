<?php declare(strict_types = 1);

namespace Tests\Integration\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\EnforceHttpsMiddleware;
use Sabservis\Api\Middleware\RequestIdMiddleware;
use Sabservis\Api\Utils\ChainBuilder;
use function is_string;

final class HttpsRequestIdPipelineIntegrationTest extends TestCase
{

	#[Test]
	public function httpRequestIsRejectedButStillReceivesRequestIdHeader(): void
	{
		$endpointCalled = false;
		$chain = $this->createPipeline($endpointCalled);

		$request = new ApiRequest(
			method: 'GET',
			uri: 'http://example.com/secure',
			serverParams: ['REMOTE_ADDR' => '198.51.100.10'],
		);

		$response = $chain($request, new ApiResponse());

		self::assertSame(400, $response->getStatusCode());
		self::assertStringContainsString('https', $response->getBody());
		self::assertNotNull($response->getHeader(RequestIdMiddleware::HeaderName));
		self::assertFalse($endpointCalled);
	}

	#[Test]
	public function trustedProxyWithForwardedHttpsPassesAndPropagatesRequestId(): void
	{
		$endpointCalled = false;
		$capturedRequestId = null;
		$chain = $this->createPipeline($endpointCalled, $capturedRequestId);

		$request = (new ApiRequest(
			method: 'GET',
			uri: '/secure',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		))->withTrustedProxies(['10.0.0.0/8']);

		$response = $chain($request, new ApiResponse());

		self::assertSame(200, $response->getStatusCode());
		self::assertTrue($endpointCalled);
		self::assertNotNull($capturedRequestId);
		self::assertSame($capturedRequestId, $response->getHeader(RequestIdMiddleware::HeaderName));
	}

	#[Test]
	public function untrustedProxyCannotBypassHttpsEnforcementWithForwardedProto(): void
	{
		$endpointCalled = false;
		$chain = $this->createPipeline($endpointCalled);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/secure',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '198.51.100.10'],
		);

		$response = $chain($request, new ApiResponse());

		self::assertSame(400, $response->getStatusCode());
		self::assertNotNull($response->getHeader(RequestIdMiddleware::HeaderName));
		self::assertFalse($endpointCalled);
	}

	#[Test]
	public function existingRequestIdHeaderIsPreservedOnSuccessfulRequest(): void
	{
		$endpointCalled = false;
		$capturedRequestId = null;
		$chain = $this->createPipeline($endpointCalled, $capturedRequestId);

		$request = new ApiRequest(
			method: 'GET',
			uri: 'https://example.com/secure',
			headers: [RequestIdMiddleware::HeaderName => 'trace-123'],
			serverParams: ['HTTPS' => 'on', 'REMOTE_ADDR' => '198.51.100.10'],
		);

		$response = $chain($request, new ApiResponse());

		self::assertSame(200, $response->getStatusCode());
		self::assertTrue($endpointCalled);
		self::assertSame('trace-123', $capturedRequestId);
		self::assertSame('trace-123', $response->getHeader(RequestIdMiddleware::HeaderName));
	}

	private function createPipeline(bool &$endpointCalled, string|null &$capturedRequestId = null): callable
	{
		$chainBuilder = new ChainBuilder();
		$chainBuilder->add(new RequestIdMiddleware());
		$chainBuilder->add(new EnforceHttpsMiddleware());
		$chainBuilder->add(
			static function (ApiRequest $request, ApiResponse $response, callable $next) use (&$endpointCalled, &$capturedRequestId): ApiResponse {
				$endpointCalled = true;
				$requestId = $request->getAttribute(RequestIdMiddleware::AttributeName);
				$capturedRequestId = is_string($requestId) ? $requestId : null;

				return $response->withStatus(200)->writeJsonBody(['ok' => true]);
			},
		);

		return $chainBuilder->create();
	}

}
