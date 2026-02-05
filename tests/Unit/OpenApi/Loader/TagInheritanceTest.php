<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;

final class TagInheritanceTest extends TestCase
{

	#[Test]
	public function methodInheritsControllerTags(): void
	{
		$spec = $this->generateSpecForController(ControllerWithTag::class);

		// Method without its own tag should inherit controller tag
		$usersOp = $spec['paths']['/users']['get'];
		self::assertContains('users', $usersOp['tags']);
	}

	#[Test]
	public function methodTagOverridesController(): void
	{
		$spec = $this->generateSpecForController(ControllerWithTagOverride::class);

		// Method with own tag should NOT have controller tag
		$healthOp = $spec['paths']['/health']['get'];
		self::assertContains('health', $healthOp['tags']);
		self::assertNotContains('users', $healthOp['tags']);

		// Method without tag should inherit controller tag
		$usersOp = $spec['paths']['/users']['get'];
		self::assertContains('users', $usersOp['tags']);
		self::assertNotContains('health', $usersOp['tags']);
	}

	#[Test]
	public function methodCanHaveMultipleTags(): void
	{
		$spec = $this->generateSpecForController(ControllerWithMultipleTags::class);

		// Method with multiple tags
		$dataOp = $spec['paths']['/data']['get'];
		self::assertContains('data', $dataOp['tags']);
		self::assertContains('reports', $dataOp['tags']);
		self::assertNotContains('controller', $dataOp['tags']);
	}

	#[Test]
	public function controllerWithoutTagMethodWithTag(): void
	{
		$spec = $this->generateSpecForController(ControllerWithoutTag::class);

		// Method has its own tag
		$dataOp = $spec['paths']['/data']['get'];
		self::assertContains('data', $dataOp['tags']);
	}

	#[Test]
	public function controllerWithoutTagMethodWithoutTag(): void
	{
		$spec = $this->generateSpecForController(ControllerWithoutAnyTag::class);

		// Method without tag on controller without tag = no tags
		$dataOp = $spec['paths']['/data']['get'];
		self::assertEmpty($dataOp['tags'] ?? []);
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
		$json = json_encode($spec);
		assert($json !== false);

		return json_decode($json, true);
	}

}

// Test controllers

#[Tag(name: 'users')]
class ControllerWithTag implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

}

#[Tag(name: 'users')]
class ControllerWithTagOverride implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

	/** @return array<mixed> */
	#[Get(path: '/health')]
	#[Tag(name: 'health')]
	public function health(): array
	{
		return [];
	}

}

#[Tag(name: 'controller')]
class ControllerWithMultipleTags implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/data')]
	#[Tag(name: 'data')]
	#[Tag(name: 'reports')]
	public function data(): array
	{
		return [];
	}

}

class ControllerWithoutTag implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/data')]
	#[Tag(name: 'data')]
	public function data(): array
	{
		return [];
	}

}

class ControllerWithoutAnyTag implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/data')]
	public function data(): array
	{
		return [];
	}

}
