<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Alias;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\PathParameter;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\UI\Controller\Controller;

final class AliasTest extends TestCase
{

	#[Test]
	public function aliasCreatesMultipleEndpoints(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithAlias::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];

		// Should have 2 endpoints: original + alias
		self::assertCount(2, $endpoints);

		// Check original endpoint
		$original = $this->findEndpointByMask($endpoints, '/employees/{id}');
		self::assertNotNull($original);
		self::assertSame(['GET'], $original['methods']);
		self::assertSame(TestControllerWithAlias::class, $original['handler']['class']);
		self::assertSame('get', $original['handler']['method']);

		// Check alias endpoint
		$alias = $this->findEndpointByMask($endpoints, '/contacts/{id}');
		self::assertNotNull($alias);
		self::assertSame(['GET'], $alias['methods']);
		self::assertSame(TestControllerWithAlias::class, $alias['handler']['class']);
		self::assertSame('get', $alias['handler']['method']);

		// Both should have same handler
		self::assertSame($original['handler'], $alias['handler']);
	}

	#[Test]
	public function multipleAliasesCreateMultipleEndpoints(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithMultipleAliases::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];

		// Should have 3 endpoints: original + 2 aliases
		self::assertCount(3, $endpoints);

		self::assertNotNull($this->findEndpointByMask($endpoints, '/employees/{id}'));
		self::assertNotNull($this->findEndpointByMask($endpoints, '/contacts/{id}'));
		self::assertNotNull($this->findEndpointByMask($endpoints, '/people/{id}'));
	}

	#[Test]
	public function aliasPreservesParameters(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithAlias::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		$alias = $this->findEndpointByMask($endpoints, '/contacts/{id}');

		self::assertNotNull($alias);
		self::assertArrayHasKey('id', $alias['parameters']);
		self::assertSame('int', $alias['parameters']['id']['type']);
	}

	#[Test]
	public function aliasHasCorrectPattern(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithAlias::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];

		$original = $this->findEndpointByMask($endpoints, '/employees/{id}');
		$alias = $this->findEndpointByMask($endpoints, '/contacts/{id}');

		// Patterns should be different (matching their respective masks)
		self::assertStringContainsString('/employees/', $original['attributes']['pattern']);
		self::assertStringContainsString('/contacts/', $alias['attributes']['pattern']);
	}

	/**
	 * @param array<array<mixed>> $endpoints
	 * @return array<mixed>|null
	 */
	private function findEndpointByMask(array $endpoints, string $mask): array|null
	{
		foreach ($endpoints as $endpoint) {
			if ($endpoint['mask'] === $mask) {
				return $endpoint;
			}
		}

		return null;
	}

}

// Test controllers

class TestControllerWithAlias implements Controller
{

	#[Get(path: '/employees/{id}')]
	#[Alias('/contacts/{id}')]
	#[PathParameter(name: 'id', type: 'int')]
	public function get(ApiRequest $request): array
	{
		return [];
	}

}

class TestControllerWithMultipleAliases implements Controller
{

	#[Get(path: '/employees/{id}')]
	#[Alias('/contacts/{id}')]
	#[Alias('/people/{id}')]
	#[PathParameter(name: 'id', type: 'int')]
	public function get(ApiRequest $request): array
	{
		return [];
	}

}
