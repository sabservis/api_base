<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\OpenApi\Controller\OpenApiController;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\Schema;

final class OpenApiControllerTest extends TestCase
{

	#[Test]
	public function returnsJsonResponse(): void
	{
		$schema = new Schema();
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$controller = new OpenApiController($schema, $config);

		$request = new ApiRequest(method: 'GET', uri: '/openapi.json');
		$response = new ApiResponse();

		$result = $controller->spec($request, $response);

		self::assertSame('application/json', $result->getHeader('Content-Type'));
	}

	#[Test]
	public function returnsStatus200(): void
	{
		$schema = new Schema();
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$controller = new OpenApiController($schema, $config);

		$request = new ApiRequest(method: 'GET', uri: '/openapi.json');
		$response = new ApiResponse();

		$result = $controller->spec($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function setsCacheControlHeader(): void
	{
		$schema = new Schema();
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$controller = new OpenApiController($schema, $config);

		$request = new ApiRequest(method: 'GET', uri: '/openapi.json');
		$response = new ApiResponse();

		$result = $controller->spec($request, $response);

		self::assertSame('public, max-age=3600', $result->getHeader('Cache-Control'));
	}

	#[Test]
	public function returnsValidOpenApiJson(): void
	{
		$schema = new Schema();
		$endpoint = new Endpoint('App\\Controller\\TestController', 'handle');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');
		$schema->addEndpoint($endpoint);

		$config = new OpenApiConfig(title: 'My API', version: '2.0.0');
		$controller = new OpenApiController($schema, $config);

		$request = new ApiRequest(method: 'GET', uri: '/openapi.json');
		$response = new ApiResponse();

		$result = $controller->spec($request, $response);
		$body = $result->getBody();

		self::assertJson($body);

		$decoded = json_decode($body, true);
		self::assertSame('3.0.3', $decoded['openapi']);
		self::assertSame('My API', $decoded['info']['title']);
		self::assertSame('2.0.0', $decoded['info']['version']);
		self::assertArrayHasKey('/users', $decoded['paths']);
	}

	#[Test]
	public function emptySchemaReturnsValidJson(): void
	{
		$schema = new Schema();
		$config = new OpenApiConfig(title: 'Empty API', version: '0.1.0');
		$controller = new OpenApiController($schema, $config);

		$request = new ApiRequest(method: 'GET', uri: '/openapi.json');
		$response = new ApiResponse();

		$result = $controller->spec($request, $response);
		$body = $result->getBody();

		self::assertJson($body);

		$decoded = json_decode($body, true);
		self::assertSame('Empty API', $decoded['info']['title']);
		self::assertArrayNotHasKey('paths', $decoded); // Empty paths are omitted from output
	}

}
