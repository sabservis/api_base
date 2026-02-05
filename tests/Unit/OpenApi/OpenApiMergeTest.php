<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\OpenApiMerge;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;

final class OpenApiMergeTest extends TestCase
{

	#[Test]
	public function mergeAddsCustomFields(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithMerge::class);

		$operation = $spec['paths']['/test']['get'];

		// Custom fields from merge should be present
		self::assertTrue($operation['deprecated']);
		self::assertSame('https://docs.example.com/test', $operation['externalDocs']['url']);
		self::assertSame('custom-value', $operation['x-custom-extension']);
	}

	#[Test]
	public function mergeOverridesGeneratedValues(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithMergeOverride::class);

		$operation = $spec['paths']['/test']['get'];

		// operationId should be overridden by merge
		self::assertSame('customOperationId', $operation['operationId']);

		// summary from merge
		self::assertSame('Custom summary', $operation['summary']);
	}

	#[Test]
	public function mergeDeepMergesResponses(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithMergeResponses::class);

		$operation = $spec['paths']['/test']['get'];

		// Original 200 response should still exist
		self::assertArrayHasKey('200', $operation['responses']);

		// Additional 418 response from merge should be added
		self::assertArrayHasKey('418', $operation['responses']);
		self::assertSame("I'm a teapot", $operation['responses']['418']['description']);
	}

	#[Test]
	public function mergeOverridesExistingResponse(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithMergeResponseOverride::class);

		$operation = $spec['paths']['/test']['get'];

		// 200 response description should be overridden
		self::assertSame('Custom success description', $operation['responses']['200']['description']);
	}

	#[Test]
	public function mergeWithoutAttributeDoesNotAddExtras(): void
	{
		$spec = $this->generateSpecForController(TestControllerWithoutMerge::class);

		$operation = $spec['paths']['/test']['get'];

		// Should not have deprecated or externalDocs
		self::assertArrayNotHasKey('deprecated', $operation);
		self::assertArrayNotHasKey('externalDocs', $operation);
		self::assertArrayNotHasKey('x-custom-extension', $operation);
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

// Test controllers

class TestControllerWithMerge implements Controller
{

	#[Get(path: '/test')]
	#[OpenApiMerge([
		'deprecated' => true,
		'externalDocs' => ['url' => 'https://docs.example.com/test'],
		'x-custom-extension' => 'custom-value',
	])]
	public function test(ApiRequest $request): array
	{
		return [];
	}

}

class TestControllerWithMergeOverride implements Controller
{

	#[Get(path: '/test')]
	#[OpenApiMerge([
		'operationId' => 'customOperationId',
		'summary' => 'Custom summary',
	])]
	public function test(ApiRequest $request): array
	{
		return [];
	}

}

class TestControllerWithMergeResponses implements Controller
{

	#[Get(path: '/test')]
	#[Response(response: '200', description: 'Success')]
	#[OpenApiMerge([
		'responses' => [
			'418' => ['description' => "I'm a teapot"],
		],
	])]
	public function test(ApiRequest $request): array
	{
		return [];
	}

}

class TestControllerWithMergeResponseOverride implements Controller
{

	#[Get(path: '/test')]
	#[Response(response: '200', description: 'Original description')]
	#[OpenApiMerge([
		'responses' => [
			'200' => ['description' => 'Custom success description'],
		],
	])]
	public function test(ApiRequest $request): array
	{
		return [];
	}

}

class TestControllerWithoutMerge implements Controller
{

	#[Get(path: '/test')]
	public function test(ApiRequest $request): array
	{
		return [];
	}

}
