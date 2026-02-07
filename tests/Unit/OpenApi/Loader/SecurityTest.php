<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Security;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function assert;
use function json_decode;
use function json_encode;

final class SecurityTest extends TestCase
{

	#[Test]
	public function controllerSecurityInheritedByMethods(): void
	{
		$spec = $this->generateSpecForController(ControllerWithSecurity::class);

		// Both methods should inherit controller security
		$usersOp = $spec['paths']['/users']['get'];
		$itemsOp = $spec['paths']['/items']['get'];

		self::assertArrayHasKey('security', $usersOp);
		self::assertSame([['Bearer' => []]], $usersOp['security']);

		self::assertArrayHasKey('security', $itemsOp);
		self::assertSame([['Bearer' => []]], $itemsOp['security']);
	}

	#[Test]
	public function methodSecurityOverridesController(): void
	{
		$spec = $this->generateSpecForController(ControllerWithMethodOverride::class);

		// Regular method inherits controller security
		$usersOp = $spec['paths']['/users']['get'];
		self::assertSame([['Bearer' => []]], $usersOp['security']);

		// Admin method has its own security
		$adminOp = $spec['paths']['/admin']['get'];
		self::assertSame([['Admin' => []]], $adminOp['security']);
	}

	#[Test]
	public function emptySecurityMakesEndpointPublic(): void
	{
		$spec = $this->generateSpecForController(ControllerWithPublicEndpoint::class);

		// Protected method has controller security
		$protectedOp = $spec['paths']['/protected']['get'];
		self::assertSame([['Bearer' => []]], $protectedOp['security']);

		// Public method has empty security (no auth required)
		$publicOp = $spec['paths']['/public']['get'];
		self::assertArrayHasKey('security', $publicOp);
		self::assertSame([], $publicOp['security']);
	}

	#[Test]
	public function inlineSecurityInOperationAttribute(): void
	{
		$spec = $this->generateSpecForController(ControllerWithInlineSecurity::class);

		// Method with inline security in #[Get] attribute
		$dataOp = $spec['paths']['/data']['get'];
		self::assertSame([['ApiKey' => []]], $dataOp['security']);

		// Public method with inline empty security
		$healthOp = $spec['paths']['/health']['get'];
		self::assertSame([], $healthOp['security']);
	}

	#[Test]
	public function multipleSecurity(): void
	{
		$spec = $this->generateSpecForController(ControllerWithMultipleSecurity::class);

		// Method with multiple security options (OR relationship)
		$dataOp = $spec['paths']['/data']['get'];
		self::assertSame([['Bearer' => []], ['ApiKey' => []]], $dataOp['security']);
	}

	#[Test]
	public function noSecurityWhenNotDefined(): void
	{
		$spec = $this->generateSpecForController(ControllerWithoutSecurity::class);

		// Method without any security definition
		$usersOp = $spec['paths']['/users']['get'];
		self::assertArrayNotHasKey('security', $usersOp);
	}

	#[Test]
	public function securityAttributeOnMethodOverridesOperation(): void
	{
		$spec = $this->generateSpecForController(ControllerWithBothSecurityTypes::class);

		// #[Security] attribute should take precedence over #[Get(security: ...)]
		// Actually, parseSecurityAttribute is called after parseOperation, so it overrides
		$dataOp = $spec['paths']['/data']['get'];
		self::assertSame([['Admin' => []]], $dataOp['security']);
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

#[Security([['Bearer' => []]])]
class ControllerWithSecurity implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

	/** @return array<mixed> */
	#[Get(path: '/items')]
	public function items(): array
	{
		return [];
	}

}

#[Security([['Bearer' => []]])]
class ControllerWithMethodOverride implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

	/** @return array<mixed> */
	#[Get(path: '/admin')]
	#[Security([['Admin' => []]])]
	public function admin(): array
	{
		return [];
	}

}

#[Security([['Bearer' => []]])]
class ControllerWithPublicEndpoint implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/protected')]
	public function protectedEndpoint(): array
	{
		return [];
	}

	/** @return array<mixed> */
	#[Get(path: '/public')]
	#[Security([])]
	public function publicEndpoint(): array
	{
		return [];
	}

}

class ControllerWithInlineSecurity implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/data', security: [['ApiKey' => []]])]
	public function data(): array
	{
		return [];
	}

	/** @return array<mixed> */
	#[Get(path: '/health', security: [])]
	public function health(): array
	{
		return [];
	}

}

class ControllerWithMultipleSecurity implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/data')]
	#[Security([['Bearer' => []], ['ApiKey' => []]])]
	public function data(): array
	{
		return [];
	}

}

class ControllerWithoutSecurity implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

}

class ControllerWithBothSecurityTypes implements Controller
{

	/**
	 * #[Security] attribute should override inline security
	 *
	 * @return array<mixed>
	 */
	#[Get(path: '/data', security: [['Bearer' => []]])]
	#[Security([['Admin' => []]])]
	public function data(): array
	{
		return [];
	}

}
