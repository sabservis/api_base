<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\Compiler;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Hidden;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\UI\Controller\Controller;

/**
 * Tests for #[Hidden] attribute integration with OpenApiAttributeLoader.
 */
final class HiddenEndpointTest extends TestCase
{

	#[Test]
	public function hiddenEndpointIsExcludedFromOpenApi(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(HiddenEndpointController::class);

		$compiler = new Compiler();
		$compiler->addConfig(['extensions' => []]);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		// Only 1 endpoint should be loaded (publicEndpoint)
		// hiddenEndpoint should be excluded
		self::assertCount(1, $result['endpoints']);

		$endpoint = $result['endpoints'][0];
		self::assertSame('/api/public', $endpoint['mask']);
		self::assertSame('publicEndpoint', $endpoint['handler']['method']);
	}

	#[Test]
	public function multipleEndpointsWithOneHidden(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(MultipleEndpointsController::class);

		$compiler = new Compiler();
		$compiler->addConfig(['extensions' => []]);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		// 2 endpoints should be loaded (list and get)
		// debug should be excluded
		self::assertCount(2, $result['endpoints']);

		$masks = array_column($result['endpoints'], 'mask');
		self::assertContains('/api/users', $masks);
		self::assertContains('/api/users/{id}', $masks);
		self::assertNotContains('/api/debug', $masks);
	}

	#[Test]
	public function allHiddenEndpointsAreExcluded(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(AllHiddenController::class);

		$compiler = new Compiler();
		$compiler->addConfig(['extensions' => []]);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		// No endpoints should be loaded
		self::assertCount(0, $result['endpoints']);
	}

}

// Test controller with hidden and public endpoints
class HiddenEndpointController implements Controller
{

	#[Get(path: '/api/debug')]
	#[Hidden(reason: 'Internal debug endpoint')]
	public function hiddenEndpoint(): array
	{
		return [];
	}

	#[Get(path: '/api/public')]
	public function publicEndpoint(): array
	{
		return [];
	}

}

// Test controller with multiple endpoints
class MultipleEndpointsController implements Controller
{

	#[Get(path: '/api/users')]
	public function list(): array
	{
		return [];
	}

	#[Get(path: '/api/users/{id}')]
	public function get(int $id): array
	{
		return [];
	}

	#[Get(path: '/api/debug')]
	#[Hidden]
	public function debug(): array
	{
		return [];
	}

}

// Test controller with all hidden endpoints
class AllHiddenController implements Controller
{

	#[Get(path: '/api/internal/health')]
	#[Hidden]
	public function health(): array
	{
		return [];
	}

	#[Get(path: '/api/internal/metrics')]
	#[Hidden]
	public function metrics(): array
	{
		return [];
	}

}
