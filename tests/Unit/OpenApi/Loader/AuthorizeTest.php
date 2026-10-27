<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\Core\Authorize;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Security\Authorizer;
use Sabservis\Api\UI\Controller\Controller;
use function array_column;

final class AuthorizeTest extends TestCase
{

	#[Test]
	public function combinesControllerAndMethodAuthorizers(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(ControllerWithAuthorizers::class);

		$result = (new OpenApiAttributeLoader($containerBuilder))->load();
		$endpoint = $result['endpoints'][0];

		self::assertArrayHasKey('authorizations', $endpoint);
		self::assertCount(2, $endpoint['authorizations']);
		self::assertSame('users.base', $endpoint['authorizations'][0]['activity']);
		self::assertSame('users.list', $endpoint['authorizations'][1]['activity']);
	}

	#[Test]
	public function inheritsAuthorizersFromParentController(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(ChildOfAuthorizedController::class);

		$result = (new OpenApiAttributeLoader($containerBuilder))->load();
		$endpoint = $result['endpoints'][0];

		self::assertSame(
			['admin.base'],
			array_column($endpoint['authorizations'], 'activity'),
		);
	}

	#[Test]
	public function accumulatesAuthorizersAcrossInheritanceChain(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(GrandchildOfAuthorizedController::class);

		$result = (new OpenApiAttributeLoader($containerBuilder))->load();
		$endpoint = $result['endpoints'][0];

		self::assertSame(
			['admin.base', 'admin.users', 'admin.users.export', 'admin.users.export.run'],
			array_column($endpoint['authorizations'], 'activity'),
		);
	}

	#[Test]
	public function throwsForMissingAuthorizerClass(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(ControllerWithMissingAuthorizer::class);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('does not exist');

		(new OpenApiAttributeLoader($containerBuilder))->load();
	}

	#[Test]
	public function throwsWhenAuthorizerDoesNotImplementInterface(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(ControllerWithInvalidAuthorizer::class);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('must implement');

		(new OpenApiAttributeLoader($containerBuilder))->load();
	}

	#[Test]
	public function throwsForEmptyActivity(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType(ControllerWithEmptyActivity::class);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('non-empty activity');

		(new OpenApiAttributeLoader($containerBuilder))->load();
	}

}

#[Authorize('users.base', TestLoaderAuthorizer::class)]
final class ControllerWithAuthorizers implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	#[Authorize('users.list', TestLoaderAuthorizer::class)]
	public function users(): array
	{
		return [];
	}

}

#[Authorize('users.base', 'Tests\\Unit\\OpenApi\\Loader\\MissingAuthorizerClass')]
final class ControllerWithMissingAuthorizer implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

}

#[Authorize('users.base', NotAnAuthorizer::class)]
final class ControllerWithInvalidAuthorizer implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

}

#[Authorize('', TestLoaderAuthorizer::class)]
final class ControllerWithEmptyActivity implements Controller
{

	/** @return array<mixed> */
	#[Get(path: '/users')]
	public function users(): array
	{
		return [];
	}

}

#[Authorize('admin.base', TestLoaderAuthorizer::class)]
abstract class AuthorizedBaseController implements Controller
{

}

final class ChildOfAuthorizedController extends AuthorizedBaseController
{

	/** @return array<mixed> */
	#[Get(path: '/admin')]
	public function index(): array
	{
		return [];
	}

}

#[Authorize('admin.users', TestLoaderAuthorizer::class)]
abstract class AuthorizedMiddleController extends AuthorizedBaseController
{

}

#[Authorize('admin.users.export', TestLoaderAuthorizer::class)]
final class GrandchildOfAuthorizedController extends AuthorizedMiddleController
{

	/** @return array<mixed> */
	#[Get(path: '/admin/users/export')]
	#[Authorize('admin.users.export.run', TestLoaderAuthorizer::class)]
	public function export(): array
	{
		return [];
	}

}

final class TestLoaderAuthorizer implements Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return true;
	}

}

final class NotAnAuthorizer
{

}
