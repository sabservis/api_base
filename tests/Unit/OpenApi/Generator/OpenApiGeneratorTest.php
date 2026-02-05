<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\EndpointResponse;
use Sabservis\Api\Schema\Schema;

final class OpenApiGeneratorTest extends TestCase
{

	private function createConfig(): OpenApiConfig
	{
		return new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			description: 'Test API description',
		);
	}

	private function createEndpoint(string $method, string $mask, string $controllerMethod = 'handle'): Endpoint
	{
		$endpoint = new Endpoint('App\\Controller\\TestController', $controllerMethod);
		$endpoint->addMethod($method);
		$endpoint->setMask($mask);

		return $endpoint;
	}

	#[Test]
	public function generatesBasicOpenApiSpec(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/users'));

		$spec = $generator->generate($schema);

		self::assertSame('3.0.3', $spec->openapi);
		self::assertSame('Test API', $spec->info->title);
		self::assertSame('1.0.0', $spec->info->version);
	}

	#[Test]
	public function generatesPathsFromEndpoints(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/users'));
		$schema->addEndpoint($this->createEndpoint('POST', '/users'));
		$schema->addEndpoint($this->createEndpoint('GET', '/users/{id}'));

		$spec = $generator->generate($schema);

		self::assertArrayHasKey('/users', $spec->paths);
		self::assertArrayHasKey('/users/{id}', $spec->paths);
		self::assertNotNull($spec->paths['/users']->get);
		self::assertNotNull($spec->paths['/users']->post);
	}

	#[Test]
	public function generatesOperationWithParameters(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('GET', '/users/{id}');
		$param = new EndpointParameter('id', 'integer');
		$param->setIn(EndpointParameter::InPath);
		$param->setDescription('User ID');
		$endpoint->addParameter($param);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users/{id}']->get;
		self::assertCount(1, $operation->parameters);
		self::assertSame('id', $operation->parameters[0]->name);
		self::assertSame('path', $operation->parameters[0]->in);
		self::assertTrue($operation->parameters[0]->required);
	}

	#[Test]
	public function generatesOperationWithQueryParameter(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('GET', '/users');
		$param = new EndpointParameter('page', 'integer');
		$param->setIn(EndpointParameter::InQuery);
		$param->setRequired(false);
		$endpoint->addParameter($param);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users']->get;
		self::assertSame('page', $operation->parameters[0]->name);
		self::assertSame('query', $operation->parameters[0]->in);
		self::assertNull($operation->parameters[0]->required); // Not required for query
	}

	#[Test]
	public function generatesOperationWithRequestBody(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('POST', '/users', 'create');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$requestBody->setRequired(true);
		$endpoint->setRequestBody($requestBody);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users']->post;
		self::assertNotNull($operation->requestBody);
		self::assertTrue($operation->requestBody->required);
		self::assertArrayHasKey('application/json', $operation->requestBody->content);
	}

	#[Test]
	public function generatesOperationWithResponses(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('GET', '/users/{id}', 'get');
		$response200 = new EndpointResponse('200', 'User found');
		$response200->setEntity(TestUserDto::class);
		$endpoint->addResponse($response200);

		$response404 = new EndpointResponse('404', 'User not found');
		$endpoint->addResponse($response404);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users/{id}']->get;
		self::assertArrayHasKey('200', $operation->responses);
		self::assertArrayHasKey('404', $operation->responses);
		self::assertSame('User found', $operation->responses['200']->description);
		self::assertSame('User not found', $operation->responses['404']->description);
	}

	#[Test]
	public function generatesDefaultResponseWhenNoneProvided(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/health'));

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/health']->get;
		self::assertArrayHasKey('200', $operation->responses);
		self::assertSame('OK', $operation->responses['200']->description);
	}

	#[Test]
	public function generatesOperationId(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('GET', '/users', 'list');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users']->get;
		// Default operation ID: controllerName (without "Controller") + method
		self::assertSame('testList', $operation->operationId);
	}

	#[Test]
	public function generatesCustomOperationId(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('GET', '/users', 'list');
		$endpoint->addTag(Endpoint::TAG_ID, 'listAllUsers');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users']->get;
		self::assertSame('listAllUsers', $operation->operationId);
	}

	#[Test]
	public function generatesInfoWithContact(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			contactName: 'Support Team',
			contactEmail: 'support@example.com',
			contactUrl: 'https://example.com/support',
		);

		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/'));

		$spec = $generator->generate($schema);

		self::assertNotNull($spec->info->contact);
		self::assertSame('Support Team', $spec->info->contact->name);
		self::assertSame('support@example.com', $spec->info->contact->email);
		self::assertSame('https://example.com/support', $spec->info->contact->url);
	}

	#[Test]
	public function generatesInfoWithLicense(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			licenseName: 'MIT',
			licenseUrl: 'https://opensource.org/licenses/MIT',
		);

		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/'));

		$spec = $generator->generate($schema);

		self::assertNotNull($spec->info->license);
		self::assertSame('MIT', $spec->info->license->name);
		self::assertSame('https://opensource.org/licenses/MIT', $spec->info->license->url);
	}

	#[Test]
	public function generatesServers(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			servers: [
				['url' => 'https://api.example.com', 'description' => 'Production'],
				['url' => 'https://staging-api.example.com', 'description' => 'Staging'],
			],
		);

		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/'));

		$spec = $generator->generate($schema);

		self::assertCount(2, $spec->servers);
		self::assertSame('https://api.example.com', $spec->servers[0]->url);
		self::assertSame('Production', $spec->servers[0]->description);
	}

	#[Test]
	public function generatesSecuritySchemes(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			securitySchemes: [
				'bearerAuth' => [
					'type' => 'http',
					'scheme' => 'bearer',
					'bearerFormat' => 'JWT',
				],
			],
		);

		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/'));

		$spec = $generator->generate($schema);

		self::assertNotNull($spec->components);
		self::assertArrayHasKey('bearerAuth', $spec->components->securitySchemes);
		self::assertSame('http', $spec->components->securitySchemes['bearerAuth']->type);
		self::assertSame('bearer', $spec->components->securitySchemes['bearerAuth']->scheme);
	}

	#[Test]
	public function generatesJson(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/users'));

		$json = $generator->generateJson($schema);

		self::assertJson($json);
		$decoded = json_decode($json, true);
		self::assertSame('3.0.3', $decoded['openapi']);
	}

	#[Test]
	public function endpointFilterExcludesEndpoints(): void
	{
		$config = $this->createConfig();
		$filter = fn (Endpoint $endpoint): bool => $endpoint->getMask() !== '/internal';

		$generator = new OpenApiGenerator($config, $filter);

		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('GET', '/users'));
		$schema->addEndpoint($this->createEndpoint('GET', '/internal'));

		$spec = $generator->generate($schema);

		self::assertArrayHasKey('/users', $spec->paths);
		self::assertArrayNotHasKey('/internal', $spec->paths);
	}

	#[Test]
	public function handlesTags(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			tags: ['users' => 'User management endpoints'],
		);

		$generator = new OpenApiGenerator($config);

		$endpoint = $this->createEndpoint('GET', '/users');
		$endpoint->addTag('users', 'User management');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		$operation = $spec->paths['/users']->get;
		self::assertContains('users', $operation->tags);

		// Tags should be in spec.tags
		self::assertNotEmpty($spec->tags);
		self::assertSame('users', $spec->tags[0]->name);
	}

	#[Test]
	public function filtersUnusedSchemas(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		// Create endpoint that uses TestUserDto
		$endpoint = $this->createEndpoint('GET', '/users/{id}');
		$response = new EndpointResponse('200', 'Success');
		$response->setEntity(TestUserDto::class);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		// Should have TestUserDto in components
		self::assertArrayHasKey('TestUserDto', $spec->components->schemas);
	}

	#[Test]
	public function skipsEndpointsWithoutMask(): void
	{
		$config = $this->createConfig();
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'handle');
		$endpoint->addMethod('GET');
		// No mask set

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);

		self::assertEmpty($spec->paths);
	}

}

// Test DTOs for OpenApiGenerator tests

class TestUserDto
{

	public int $id;

	public string $name;

	public string $email;

}

class TestCreateUserDto
{

	public string $name;

	public string $email;

}
