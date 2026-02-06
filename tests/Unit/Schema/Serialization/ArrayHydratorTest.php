<?php declare(strict_types = 1);

namespace Tests\Unit\Schema\Serialization;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\Security\Authorizer;
use function array_values;

final class ArrayHydratorTest extends TestCase
{

	#[Test]
	public function hydrateNonArrayThrows(): void
	{
		$hydrator = new ArrayHydrator();

		$this->expectException(InvalidArgumentException::class);

		$hydrator->hydrate('not an array');
	}

	#[Test]
	public function hydrateMissingHandlerThrows(): void
	{
		$hydrator = new ArrayHydrator();

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage("Schema route 'handler' is required");

		$hydrator->hydrate([
			'endpoints' => [
				['methods' => ['GET'], 'mask' => '/test'],
			],
		]);
	}

	#[Test]
	public function hydrateBasicEndpoint(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'index'],
					'methods' => ['GET'],
					'mask' => '/test',
				],
			],
		];

		$schema = $hydrator->hydrate($data);

		self::assertInstanceOf(Schema::class, $schema);
		self::assertCount(1, $schema->getEndpoints());

		$endpoint = $schema->getEndpoints()[0];
		self::assertSame(['GET'], $endpoint->getMethods());
		self::assertSame('/test', $endpoint->getMask());
		self::assertSame('TestController', $endpoint->getControllerClass());
		self::assertSame('index', $endpoint->getControllerMethod());
	}

	#[Test]
	public function hydrateEndpointWithParameters(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'show'],
					'methods' => ['GET'],
					'mask' => '/users/{id}',
					'attributes' => ['pattern' => '/users/(?P<id>[^/]+)'],
					'parameters' => [
						[
							'name' => 'id',
							'type' => 'int',
							'in' => 'path',
							'required' => true,
							'deprecated' => false,
							'allowEmpty' => false,
							'description' => 'User ID',
							'denormalizer' => null,
						],
					],
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$endpoint = $schema->getEndpoints()[0];

		self::assertCount(1, $endpoint->getParameters());

		$param = $endpoint->getParameters()['id'];
		self::assertSame('id', $param->getName());
		self::assertSame('int', $param->getType());
		self::assertSame(EndpointParameter::InPath, $param->getIn());
		self::assertTrue($param->isRequired());
		self::assertSame('User ID', $param->getDescription());
	}

	#[Test]
	public function hydrateEndpointWithRequestBody(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'create'],
					'methods' => ['POST'],
					'mask' => '/users',
					'requestBody' => [
						'entity' => 'App\\DTO\\CreateUserDTO',
						'required' => true,
						'description' => 'User data',
					],
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$endpoint = $schema->getEndpoints()[0];

		self::assertNotNull($endpoint->getRequestBody());
		self::assertSame('App\\DTO\\CreateUserDTO', $endpoint->getRequestBody()->getEntity());
		self::assertTrue($endpoint->getRequestBody()->isRequired());
		self::assertSame('User data', $endpoint->getRequestBody()->getDescription());
	}

	#[Test]
	public function hydrateEndpointWithAuthorizations(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'create'],
					'methods' => ['POST'],
					'mask' => '/users',
					'authorizations' => [
						[
							'activity' => 'users.create',
							'authorizer' => TestArrayHydratorAuthorizer::class,
						],
					],
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$endpoint = $schema->getEndpoints()[0];

		self::assertTrue($endpoint->hasAuthorizations());
		self::assertCount(1, $endpoint->getAuthorizations());
		self::assertSame('users.create', array_values($endpoint->getAuthorizations())[0]->getActivity());
	}

	#[Test]
	public function hydrateEndpointWithResponses(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'index'],
					'methods' => ['GET'],
					'mask' => '/users',
					'responses' => [
						[
							'code' => '200',
							'description' => 'Success',
							'entity' => 'App\\DTO\\UserDTO[]',
						],
						[
							'code' => '404',
							'description' => 'Not found',
						],
					],
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$endpoint = $schema->getEndpoints()[0];
		$responses = $endpoint->getResponses();

		self::assertCount(2, $responses);
		self::assertSame('200', $responses['200']->getCode());
		self::assertSame('Success', $responses['200']->getDescription());
		self::assertSame('App\\DTO\\UserDTO[]', $responses['200']->getEntity());
		self::assertSame('404', $responses['404']->getCode());
	}

	#[Test]
	public function hydrateEndpointWithTags(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'index'],
					'methods' => ['GET'],
					'mask' => '/users',
					'tags' => [
						'users' => 'Users API',
						'public' => null,
					],
					'id' => 'users.list',
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$endpoint = $schema->getEndpoints()[0];

		self::assertTrue($endpoint->hasTag('users'));
		self::assertTrue($endpoint->hasTag('public'));
		self::assertSame('Users API', $endpoint->getTag('users'));
	}

	#[Test]
	public function hydrateWithSchemaTags(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'index'],
					'methods' => ['GET'],
					'mask' => '/test',
				],
			],
			'tags' => [
				'users' => ['name' => 'users', 'description' => 'User operations'],
			],
		];

		$schema = $hydrator->hydrate($data);
		$tags = $schema->getTags();

		self::assertCount(1, $tags);
		self::assertArrayHasKey('users', $tags);
	}

	#[Test]
	public function hydrateLegacyFormat(): void
	{
		$hydrator = new ArrayHydrator();

		// Legacy format - array of endpoints without 'endpoints' key
		$data = [
			[
				'handler' => ['class' => 'TestController', 'method' => 'index'],
				'methods' => ['GET'],
				'mask' => '/test',
			],
		];

		$schema = $hydrator->hydrate($data);

		self::assertCount(1, $schema->getEndpoints());
	}

	#[Test]
	public function hydrateEndpointParameterWithExample(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'search'],
					'methods' => ['GET'],
					'mask' => '/search',
					'parameters' => [
						[
							'name' => 'query',
							'type' => 'string',
							'in' => 'query',
							'required' => false,
							'deprecated' => false,
							'allowEmptyValue' => false,
							'description' => 'Search term',
							'example' => 'laptop',
						],
					],
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$param = $schema->getEndpoints()[0]->getParameters()['query'];

		self::assertSame('laptop', $param->getExample());
	}

	#[Test]
	public function hydrateEndpointParameterWithStyleAndExplode(): void
	{
		$hydrator = new ArrayHydrator();

		$data = [
			'endpoints' => [
				[
					'handler' => ['class' => 'TestController', 'method' => 'list'],
					'methods' => ['GET'],
					'mask' => '/items',
					'parameters' => [
						[
							'name' => 'ids',
							'type' => 'string',
							'in' => 'query',
							'required' => false,
							'deprecated' => false,
							'allowEmptyValue' => false,
							'description' => null,
							'style' => 'form',
							'explode' => true,
						],
					],
				],
			],
		];

		$schema = $hydrator->hydrate($data);
		$param = $schema->getEndpoints()[0]->getParameters()['ids'];

		self::assertSame('form', $param->getStyle());
		self::assertTrue($param->getExplode());
	}

}

final class TestArrayHydratorAuthorizer implements Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return true;
	}

}
