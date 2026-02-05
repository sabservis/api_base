<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;

final class OneOfResponseTest extends TestCase
{

	#[Test]
	public function itemsToOpenApiSpecGeneratesOneOf(): void
	{
		$items = new Items(
			oneOf: [
				ApplicationDto::class,
				TombstoneDto::class,
			],
		);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('oneOf', $spec);
		self::assertCount(2, $spec['oneOf']);
		self::assertSame(['$ref' => '#/components/schemas/ApplicationDto'], $spec['oneOf'][0]);
		self::assertSame(['$ref' => '#/components/schemas/TombstoneDto'], $spec['oneOf'][1]);
	}

	#[Test]
	public function jsonContentToOpenApiSpecGeneratesArrayWithOneOfItems(): void
	{
		$jsonContent = new JsonContent(
			type: 'array',
			items: new Items(
				oneOf: [
					ApplicationDto::class,
					TombstoneDto::class,
				],
			),
		);

		$spec = $jsonContent->toOpenApiSpec();

		self::assertArrayHasKey('schema', $spec);
		self::assertSame('array', $spec['schema']['type']);
		self::assertArrayHasKey('items', $spec['schema']);
		self::assertArrayHasKey('oneOf', $spec['schema']['items']);
		self::assertCount(2, $spec['schema']['items']['oneOf']);
	}

	#[Test]
	public function responseWithJsonContentGeneratesCorrectSpec(): void
	{
		$response = new Response(
			response: 200,
			description: 'List of applications or tombstones',
			content: new JsonContent(
				type: 'array',
				items: new Items(
					oneOf: [
						ApplicationDto::class,
						TombstoneDto::class,
					],
				),
			),
		);

		$spec = $response->toOpenApiSpec();

		self::assertSame('List of applications or tombstones', $spec['description']);
		self::assertArrayHasKey('content', $spec);
		self::assertArrayHasKey('application/json', $spec['content']);

		$schema = $spec['content']['application/json']['schema'];
		self::assertSame('array', $schema['type']);
		self::assertArrayHasKey('oneOf', $schema['items']);
		self::assertSame(['$ref' => '#/components/schemas/ApplicationDto'], $schema['items']['oneOf'][0]);
		self::assertSame(['$ref' => '#/components/schemas/TombstoneDto'], $schema['items']['oneOf'][1]);
	}

	#[Test]
	public function fullGeneratorFlowWithOneOfResponse(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithOneOfResponse::class);

		$operation = $spec['paths']['/applications/download']['get'];

		self::assertArrayHasKey('200', $operation['responses']);

		$responseContent = $operation['responses']['200']['content']['application/json'];
		self::assertSame('array', $responseContent['schema']['type']);
		self::assertArrayHasKey('oneOf', $responseContent['schema']['items']);
		self::assertCount(2, $responseContent['schema']['items']['oneOf']);

		// Verify refs are correct
		$oneOf = $responseContent['schema']['items']['oneOf'];
		self::assertSame('#/components/schemas/ApplicationDto', $oneOf[0]['$ref']);
		self::assertSame('#/components/schemas/TombstoneDto', $oneOf[1]['$ref']);
	}

	#[Test]
	public function oneOfReferencesAreRegisteredInComponentsSchemas(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithOneOfResponse::class);

		// Verify that the referenced schemas are registered in components.schemas
		self::assertArrayHasKey('components', $spec);
		self::assertArrayHasKey('schemas', $spec['components']);
		self::assertArrayHasKey('ApplicationDto', $spec['components']['schemas']);
		self::assertArrayHasKey('TombstoneDto', $spec['components']['schemas']);

		// Verify schema structure
		self::assertSame('object', $spec['components']['schemas']['ApplicationDto']['type']);
		self::assertSame('object', $spec['components']['schemas']['TombstoneDto']['type']);
	}

	#[Test]
	public function itemsGetReferencedClassesReturnsClassStringsWithBackslash(): void
	{
		// Test that getReferencedClasses() works with fully-qualified class names
		// even without checking class_exists() - this is important because during
		// DI compilation, classes might not be autoloaded yet
		$items = new Items(
			oneOf: [
				'App\\Response\\SomeResponse',
				'App\\Response\\AnotherResponse',
			],
		);

		$classes = $items->getReferencedClasses();

		self::assertCount(2, $classes);
		self::assertSame('App\\Response\\SomeResponse', $classes[0]);
		self::assertSame('App\\Response\\AnotherResponse', $classes[1]);
	}

	#[Test]
	public function jsonContentGetReferencedClassesIncludesItemsOneOf(): void
	{
		// Test that JsonContent->getReferencedClasses() includes classes from Items->oneOf
		$jsonContent = new JsonContent(
			type: 'array',
			items: new Items(
				oneOf: [
					'App\\Response\\FirstDto',
					'App\\Response\\SecondDto',
				],
			),
		);

		$classes = $jsonContent->getReferencedClasses();

		self::assertCount(2, $classes);
		self::assertContains('App\\Response\\FirstDto', $classes);
		self::assertContains('App\\Response\\SecondDto', $classes);
	}

	#[Test]
	public function itemsWithAnyOfGeneratesCorrectSpec(): void
	{
		$items = new Items(
			anyOf: [
				ApplicationDto::class,
				TombstoneDto::class,
			],
		);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('anyOf', $spec);
		self::assertCount(2, $spec['anyOf']);
	}

	#[Test]
	public function itemsWithAllOfGeneratesCorrectSpec(): void
	{
		$items = new Items(
			allOf: [
				ApplicationDto::class,
				TombstoneDto::class,
			],
		);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('allOf', $spec);
		self::assertCount(2, $spec['allOf']);
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<string, mixed>
	 */
	private function generateSpecForController(string $controllerClass): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType($controllerClass);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$spec = $generator->generate($schema);

		return json_decode(json_encode($spec), true);
	}

}

// Test DTOs

class ApplicationDto
{

	public int $id;

	public string $name;

}

class TombstoneDto
{

	public int $id;

	public bool $deleted;

}

// Test controller

class TestControllerWithOneOfResponse implements Controller
{

	#[Get(path: '/applications/download')]
	#[Response(
		response: 200,
		description: 'List of applications or tombstones',
		content: new JsonContent(
			type: 'array',
			items: new Items(
				oneOf: [
					ApplicationDto::class,
					TombstoneDto::class,
				],
			),
		),
	)]
	public function download(ApiRequest $request): array
	{
		return [];
	}

}
