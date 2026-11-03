<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\UI\Controller\Controller;

final class DuplicateRouteTest extends TestCase
{

	#[Test]
	public function duplicateRouteAcrossControllersFailsAtLoadTime(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('a')->setType(DuplicateRouteControllerA::class);
		$containerBuilder->addDefinition('b')->setType(DuplicateRouteControllerB::class);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('Duplicate route GET /dup/{key}');

		(new OpenApiAttributeLoader($containerBuilder))->load();
	}

	#[Test]
	public function sameMaskWithDifferentMethodsLoads(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('c')->setType(DistinctMethodsController::class);

		$result = (new OpenApiAttributeLoader($containerBuilder))->load();

		self::assertCount(2, $result['endpoints']);
	}

}

final class DuplicateRouteControllerA implements Controller
{

	#[Get(path: '/dup/{id}')]
	public function byId(int $id): void
	{
		// fixture only
	}

}

final class DuplicateRouteControllerB implements Controller
{

	/**
	 * Same shape as /dup/{id} - only the variable name differs.
	 */
	#[Get(path: '/dup/{key}')]
	public function byKey(string $key): void
	{
		// fixture only
	}

}

final class DistinctMethodsController implements Controller
{

	#[Get(path: '/items')]
	public function list(): void
	{
		// fixture only
	}

	#[Post(path: '/items')]
	public function create(): void
	{
		// fixture only
	}

}
