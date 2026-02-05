<?php declare(strict_types = 1);

namespace Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Testing\ApiTestClient;

final class ApiTestClientTest extends TestCase
{

	#[Test]
	public function getRequestReturnsTestResponse(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response
				->withStatus(200)
				->writeJsonBody(['id' => 1, 'name' => 'Test']);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/users/1');

		self::assertSame(200, $response->getStatusCode());
		self::assertSame(['id' => 1, 'name' => 'Test'], $response->json());
	}

	#[Test]
	public function postRequestWithJsonBody(): void
	{
		$receivedBody = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedBody): ApiResponse {
			$receivedBody = $request->getJsonBody();
			return $response->withStatus(201)->writeJsonBody(['id' => 1]);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->postJson('/users', ['name' => 'John', 'email' => 'john@example.com']);

		self::assertSame(201, $response->getStatusCode());
		self::assertSame(['name' => 'John', 'email' => 'john@example.com'], $receivedBody);
	}

	#[Test]
	public function putJsonRequestSendsCorrectBody(): void
	{
		$receivedBody = null;
		$receivedMethod = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedBody, &$receivedMethod): ApiResponse {
			$receivedBody = $request->getJsonBody();
			$receivedMethod = $request->getMethod();
			return $response->withStatus(200)->writeJsonBody(['updated' => true]);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->putJson('/users/1', ['name' => 'Jane']);

		self::assertSame('PUT', $receivedMethod);
		self::assertSame(['name' => 'Jane'], $receivedBody);
		self::assertSame(200, $response->getStatusCode());
	}

	#[Test]
	public function patchJsonRequestSendsCorrectBody(): void
	{
		$receivedBody = null;
		$receivedMethod = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedBody, &$receivedMethod): ApiResponse {
			$receivedBody = $request->getJsonBody();
			$receivedMethod = $request->getMethod();
			return $response->withStatus(200)->writeJsonBody(['patched' => true]);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->patchJson('/users/1', ['status' => 'active']);

		self::assertSame('PATCH', $receivedMethod);
		self::assertSame(['status' => 'active'], $receivedBody);
	}

	#[Test]
	public function deleteRequestSendsCorrectMethod(): void
	{
		$receivedMethod = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedMethod): ApiResponse {
			$receivedMethod = $request->getMethod();
			return $response->withStatus(204);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->delete('/users/1');

		self::assertSame('DELETE', $receivedMethod);
		self::assertSame(204, $response->getStatusCode());
	}

	#[Test]
	public function withHeadersSetsDefaultHeaders(): void
	{
		$receivedHeaders = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedHeaders): ApiResponse {
			$receivedHeaders = $request->getHeaders();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);
		$client->withHeaders(['x-api-key' => 'secret123']);

		$client->get('/users');

		self::assertSame('secret123', $receivedHeaders['x-api-key'] ?? null);
	}

	#[Test]
	public function withTokenSetsAuthorizationHeader(): void
	{
		$receivedHeaders = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedHeaders): ApiResponse {
			$receivedHeaders = $request->getHeaders();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);
		$client->withToken('jwt-token-123');

		$client->get('/protected');

		self::assertSame('Bearer jwt-token-123', $receivedHeaders['authorization'] ?? null);
	}

	#[Test]
	public function requestHeadersOverrideDefaultHeaders(): void
	{
		$receivedHeaders = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedHeaders): ApiResponse {
			$receivedHeaders = $request->getHeaders();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);
		$client->withHeaders(['x-custom' => 'default']);

		$client->get('/users', ['x-custom' => 'override']);

		self::assertSame('override', $receivedHeaders['x-custom'] ?? null);
	}

	#[Test]
	public function testResponseAssertOk(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200)->writeJsonBody(['success' => true]);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/status');

		// Should not throw
		$response->assertOk();
		$response->assertStatus(200);
	}

	#[Test]
	public function testResponseAssertCreated(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(201)->writeJsonBody(['id' => 1]);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->postJson('/users', ['name' => 'Test']);

		$response->assertCreated();
	}

	#[Test]
	public function testResponseAssertNotFound(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(404)->writeJsonBody(['error' => 'Not found']);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/users/999');

		$response->assertNotFound();
	}

	#[Test]
	public function testResponseAssertJson(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200)->writeJsonBody(['id' => 1, 'name' => 'Test']);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/users/1');

		$response->assertJson(['id' => 1, 'name' => 'Test']);
	}

	#[Test]
	public function testResponseAssertJsonContains(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200)->writeJsonBody(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/users/1');

		$response->assertJsonContains(['id' => 1, 'name' => 'Test']);
	}

	#[Test]
	public function testResponseAssertJsonHasKey(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200)->writeJsonBody(['id' => 1, 'name' => 'Test']);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/users/1');

		$response->assertJsonHasKey('id');
		$response->assertJsonHasKey('name');
	}

	#[Test]
	public function testResponseAssertHeader(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response
				->withStatus(200)
				->withHeader('x-custom', 'value123')
				->writeJsonBody([]);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/');

		$response->assertHeader('x-custom', 'value123');
		$response->assertHeaderExists('x-custom');
	}

	#[Test]
	public function testResponseChainableAssertions(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response
				->withStatus(200)
				->withHeader('content-type', 'application/json')
				->writeJsonBody(['id' => 1, 'name' => 'Test']);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/users/1');

		// Chained assertions
		$response
			->assertOk()
			->assertJsonHasKey('id')
			->assertJsonContains(['name' => 'Test'])
			->assertHeaderExists('content-type');
	}

	#[Test]
	public function postWithRawBodySendsCorrectData(): void
	{
		$receivedBody = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedBody): ApiResponse {
			$receivedBody = $request->getContents();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$client->post('/webhook', '<xml>data</xml>', ['content-type' => 'application/xml']);

		self::assertSame('<xml>data</xml>', $receivedBody);
	}

	#[Test]
	public function putWithRawBodySendsCorrectData(): void
	{
		$receivedBody = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedBody): ApiResponse {
			$receivedBody = $request->getContents();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$client->put('/data', 'raw-content');

		self::assertSame('raw-content', $receivedBody);
	}

	#[Test]
	public function patchWithRawBodySendsCorrectData(): void
	{
		$receivedBody = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedBody): ApiResponse {
			$receivedBody = $request->getContents();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$client->patch('/data', 'patch-content');

		self::assertSame('patch-content', $receivedBody);
	}

	#[Test]
	public function customRequestMethod(): void
	{
		$receivedMethod = null;

		$chain = function (ApiRequest $request, ApiResponse $response, callable $next) use (&$receivedMethod): ApiResponse {
			$receivedMethod = $request->getMethod();
			return $response->withStatus(200);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$client->request('OPTIONS', '/cors-check');

		self::assertSame('OPTIONS', $receivedMethod);
	}

	#[Test]
	public function getBodyReturnsRawOutput(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200)->writeBody('plain text response');
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/plain');

		self::assertSame('plain text response', $response->getBody());
	}

	#[Test]
	public function getResponseReturnsOriginalApiResponse(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response
				->withStatus(200)
				->withAttribute('custom', 'value');
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/');

		$originalResponse = $response->getResponse();

		self::assertInstanceOf(ApiResponse::class, $originalResponse);
		self::assertSame('value', $originalResponse->getAttribute('custom'));
	}

	#[Test]
	public function jsonReturnsNullForNonJsonBody(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(200)->writeBody('not json');
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->get('/plain');

		self::assertNull($response->json());
	}

	#[Test]
	public function jsonReturnsNullForEmptyBody(): void
	{
		$chain = function (ApiRequest $request, ApiResponse $response, callable $next): ApiResponse {
			return $response->withStatus(204);
		};

		$app = new ApiApplication($chain);
		$client = new ApiTestClient($app);

		$response = $client->delete('/users/1');

		self::assertNull($response->json());
	}

}
