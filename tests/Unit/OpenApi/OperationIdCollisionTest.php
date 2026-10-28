<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Tests\Unit\OpenApi\Fixtures\OperationId\Admin\UserController as AdminUserController;
use Tests\Unit\OpenApi\Fixtures\OperationId\ContactController;
use Tests\Unit\OpenApi\Fixtures\OperationId\Front\UserController as FrontUserController;
use Tests\Unit\OpenApi\Fixtures\OperationId\PingController;
use function array_count_values;
use function array_filter;
use function json_decode;
use function json_encode;
use function strtoupper;
use const JSON_THROW_ON_ERROR;

final class OperationIdCollisionTest extends TestCase
{

	#[Test]
	public function sameControllerNameInDifferentNamespacesGetsNamespacePrefix(): void
	{
		$ids = $this->operationIds();

		self::assertSame('adminUserList', $ids['GET /admin/users']);
		self::assertSame('frontUserList', $ids['GET /front/users']);
	}

	#[Test]
	public function methodRegisteredForSeveralHttpMethodsGetsMethodSuffix(): void
	{
		$ids = $this->operationIds();

		self::assertSame('pingCheckGet', $ids['GET /ping']);
		self::assertSame('pingCheckPost', $ids['POST /ping']);
	}

	#[Test]
	public function nonCollidingAndExplicitIdsAreUnchanged(): void
	{
		$ids = $this->operationIds();

		self::assertSame('pingStatus', $ids['GET /status']);
		self::assertSame('userList', $ids['GET /legacy/users']);
	}

	#[Test]
	public function aliasesOfOneEndpointGetNumericSuffix(): void
	{
		$ids = $this->operationIds();

		self::assertSame('contactList', $ids['GET /contacts']);
		self::assertSame('contactList2', $ids['GET /people']);
		self::assertSame('contactList3', $ids['GET /persons']);
	}

	#[Test]
	public function allOperationIdsAreUnique(): void
	{
		$duplicates = array_filter(
			array_count_values($this->operationIds()),
			static fn (int $count): bool => $count > 1,
		);

		self::assertSame([], $duplicates);
	}

	/**
	 * @return array<string, string> "METHOD /path" => operationId
	 */
	private function operationIds(): array
	{
		$containerBuilder = new ContainerBuilder();

		foreach ([AdminUserController::class, FrontUserController::class, PingController::class, ContactController::class] as $i => $class) {
			$containerBuilder->addDefinition('controller' . $i)->setType($class);
		}

		$schema = (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());
		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'Test API', version: '1.0.0')))->generate($schema);
		$data = json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

		$ids = [];

		foreach ($data['paths'] as $path => $operations) {
			foreach ($operations as $method => $operation) {
				$ids[strtoupper($method) . ' ' . $path] = $operation['operationId'];
			}
		}

		return $ids;
	}

}
