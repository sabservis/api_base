<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use DateTimeImmutable;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\PathParameter;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Put;
use Sabservis\Api\Attribute\OpenApi\QueryParameter;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\UI\Controller\Controller;

final class ParameterInferenceTest extends TestCase
{

	#[Test]
	public function infersPathParameterFromMethodSignature(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(InferredPathParamController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		self::assertCount(1, $endpoints);

		$endpoint = $endpoints[0];
		self::assertArrayHasKey('id', $endpoint['parameters']);

		$param = $endpoint['parameters']['id'];
		self::assertSame('id', $param['name']);
		self::assertSame('int', $param['type']);
		self::assertSame(EndpointParameter::InPath, $param['in']);
		self::assertTrue($param['required']);
	}

	#[Test]
	public function infersQueryParameterFromMethodSignature(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(InferredQueryParamController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// limit should be inferred as query param (not in path)
		self::assertArrayHasKey('limit', $endpoint['parameters']);

		$param = $endpoint['parameters']['limit'];
		self::assertSame('limit', $param['name']);
		self::assertSame('int', $param['type']);
		self::assertSame(EndpointParameter::InQuery, $param['in']);
		self::assertTrue($param['required']); // not nullable, no default
	}

	#[Test]
	public function infersOptionalQueryParameter(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(InferredOptionalParamController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// offset has default value, should be optional
		self::assertArrayHasKey('offset', $endpoint['parameters']);

		$param = $endpoint['parameters']['offset'];
		self::assertSame('offset', $param['name']);
		self::assertSame('int', $param['type']);
		self::assertSame(EndpointParameter::InQuery, $param['in']);
		self::assertFalse($param['required']); // has default value
	}

	#[Test]
	public function infersNullableQueryParameter(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(InferredNullableParamController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// filter is nullable, should be optional
		self::assertArrayHasKey('filter', $endpoint['parameters']);

		$param = $endpoint['parameters']['filter'];
		self::assertSame('filter', $param['name']);
		self::assertSame('string', $param['type']);
		self::assertFalse($param['required']); // nullable
	}

	#[Test]
	public function explicitAttributeTakesPrecedence(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ExplicitOverrideController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// id has explicit attribute with description
		$param = $endpoint['parameters']['id'];
		self::assertSame('User ID', $param['description']);
		self::assertSame('int', $param['type']);
	}

	#[Test]
	public function skipsApiRequestAndApiResponse(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(MixedParamsController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// Should only have 'id' parameter, not 'request' or 'response'
		self::assertCount(1, $endpoint['parameters']);
		self::assertArrayHasKey('id', $endpoint['parameters']);
		self::assertArrayNotHasKey('request', $endpoint['parameters']);
		self::assertArrayNotHasKey('response', $endpoint['parameters']);
	}

	#[Test]
	public function infersComplexTypes(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ComplexTypesController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// DateTimeImmutable parameter
		self::assertArrayHasKey('date', $endpoint['parameters']);
		$dateParam = $endpoint['parameters']['date'];
		self::assertSame(DateTimeImmutable::class, $dateParam['type']);
	}

	#[Test]
	public function infersMultipleParameters(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(MultipleParamsController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// Should have all parameters
		self::assertArrayHasKey('id', $endpoint['parameters']);
		self::assertArrayHasKey('limit', $endpoint['parameters']);
		self::assertArrayHasKey('offset', $endpoint['parameters']);

		// id is path param (in URL)
		self::assertSame(EndpointParameter::InPath, $endpoint['parameters']['id']['in']);

		// limit and offset are query params
		self::assertSame(EndpointParameter::InQuery, $endpoint['parameters']['limit']['in']);
		self::assertSame(EndpointParameter::InQuery, $endpoint['parameters']['offset']['in']);
	}

	#[Test]
	public function parameterWithSchemaUsesSchemaType(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(SchemaParameterController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		// offset parameter has schema with type 'integer'
		self::assertArrayHasKey('offset', $endpoint['parameters']);
		$param = $endpoint['parameters']['offset'];

		// Type should be extracted from schema
		self::assertSame('integer', $param['type']);
		self::assertArrayHasKey('schemaSpec', $param);
		self::assertNotNull($param['schemaSpec']);
		self::assertSame('integer', $param['schemaSpec']['type']);
		self::assertSame(0, $param['schemaSpec']['minimum']);
	}

	#[Test]
	public function parameterWithSchemaEnumPreservesEnum(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(SchemaEnumParameterController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$endpoint = $endpoints[0];

		self::assertArrayHasKey('status', $endpoint['parameters']);
		$param = $endpoint['parameters']['status'];

		self::assertSame('string', $param['type']);
		self::assertArrayHasKey('schemaSpec', $param);
		self::assertSame(['active', 'inactive', 'pending'], $param['schemaSpec']['enum']);
	}

	#[Test]
	public function explicitRequestBodyDtoIsNotInferredAsQueryParameter(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ExplicitRequestBodyController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];

		self::assertArrayHasKey('requestBody', $endpoint);
		self::assertSame(RequestBodyCreateDto::class, $endpoint['requestBody']['entity']);
		self::assertArrayNotHasKey('input', $endpoint['parameters']);
	}

	#[Test]
	public function infersRequestBodyFromDtoParameterForPostMethod(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(InferredRequestBodyController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];

		self::assertArrayHasKey('requestBody', $endpoint);
		self::assertSame(RequestBodyCreateDto::class, $endpoint['requestBody']['entity']);
		self::assertArrayNotHasKey('input', $endpoint['parameters']);
	}

	#[Test]
	public function infersRequestBodyFromDtoParameterAndKeepsPathParameter(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(InferredRequestBodyWithPathController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];

		self::assertArrayHasKey('id', $endpoint['parameters']);
		self::assertSame(EndpointParameter::InPath, $endpoint['parameters']['id']['in']);
		self::assertArrayNotHasKey('input', $endpoint['parameters']);
		self::assertArrayHasKey('requestBody', $endpoint);
		self::assertSame(RequestBodyUpdateDto::class, $endpoint['requestBody']['entity']);
	}

}

// Test controllers

class InferredPathParamController implements Controller
{

	#[Get(path: '/users/{id}')]
	public function get(int $id): array
	{
		return [];
	}

}

class InferredQueryParamController implements Controller
{

	#[Get(path: '/users')]
	public function list(int $limit): array
	{
		return [];
	}

}

class InferredOptionalParamController implements Controller
{

	#[Get(path: '/users')]
	public function list(int $offset = 0): array
	{
		return [];
	}

}

class InferredNullableParamController implements Controller
{

	#[Get(path: '/users')]
	public function list(string|null $filter): array
	{
		return [];
	}

}

class ExplicitOverrideController implements Controller
{

	#[Get(path: '/users/{id}')]
	#[PathParameter(name: 'id', type: 'int', description: 'User ID')]
	public function get(int $id): array
	{
		return [];
	}

}

class MixedParamsController implements Controller
{

	#[Get(path: '/users/{id}')]
	public function get(int $id, ApiRequest $request, ApiResponse $response): array
	{
		return [];
	}

}

class ComplexTypesController implements Controller
{

	#[Get(path: '/events')]
	public function list(DateTimeImmutable $date): array
	{
		return [];
	}

}

class MultipleParamsController implements Controller
{

	#[Get(path: '/users/{id}/items')]
	public function list(int $id, int $limit = 20, int $offset = 0): array
	{
		return [];
	}

}

class SchemaParameterController implements Controller
{

	#[Get(path: '/items')]
	#[QueryParameter(name: 'offset', schema: new Schema(type: 'integer', minimum: 0))]
	public function list(): array
	{
		return [];
	}

}

class SchemaEnumParameterController implements Controller
{

	#[Get(path: '/items')]
	#[QueryParameter(name: 'status', schema: new Schema(type: 'string', enum: ['active', 'inactive', 'pending']))]
	public function list(): array
	{
		return [];
	}

}

class ExplicitRequestBodyController implements Controller
{

	#[Post(path: '/users')]
	#[RequestBody(ref: RequestBodyCreateDto::class, required: true)]
	public function create(RequestBodyCreateDto $input): array
	{
		return [];
	}

}

class InferredRequestBodyController implements Controller
{

	#[Post(path: '/users')]
	public function create(RequestBodyCreateDto $input): array
	{
		return [];
	}

}

class InferredRequestBodyWithPathController implements Controller
{

	#[Put(path: '/users/{id}')]
	public function update(int $id, RequestBodyUpdateDto $input): array
	{
		return [];
	}

}

class RequestBodyCreateDto
{

	public string $name;

}

class RequestBodyUpdateDto
{

	public string $name;

}
