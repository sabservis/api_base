<?php declare(strict_types = 1);

namespace Tests\Integration\Authorization;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\Core\Authorize;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\Security\AuthorizationChecker;
use Sabservis\Api\Security\Authorizer;
use Sabservis\Api\UI\Controller\Controller;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class AuthorizationPipelineIntegrationTest extends TestCase
{

	#[Test]
	public function allowedRequestPassesAuthorizationAndReachesController(): void
	{
		$controller = new AuthorizationIntegrationController();
		$dispatcher = $this->createDispatcher(
			controller: $controller,
			controllerClass: AuthorizationIntegrationController::class,
			authorizers: [
				ToggleAuthorizationIntegrationAuthorizer::class => new ToggleAuthorizationIntegrationAuthorizer(true),
			],
		);

		$result = $dispatcher->dispatch(
			new ApiRequest(method: 'GET', uri: '/secure'),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"ok":true}', $result->getBody());
		self::assertSame(1, $controller->calls);
	}

	#[Test]
	public function deniedRequestStopsBeforeControllerExecution(): void
	{
		$controller = new AuthorizationIntegrationController();
		$dispatcher = $this->createDispatcher(
			controller: $controller,
			controllerClass: AuthorizationIntegrationController::class,
			authorizers: [
				ToggleAuthorizationIntegrationAuthorizer::class => new ToggleAuthorizationIntegrationAuthorizer(false),
			],
		);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(403);
		$this->expectExceptionMessage('secure.read');

		try {
			$dispatcher->dispatch(
				new ApiRequest(method: 'GET', uri: '/secure'),
				new ApiResponse(),
			);
		} finally {
			self::assertSame(0, $controller->calls);
		}
	}

	#[Test]
	public function requestWithControllerAndMethodAuthorizersPassesWhenBothAllow(): void
	{
		$controller = new DualAuthorizationIntegrationController();
		$controllerAuthorizer = new ToggleControllerAuthorizationIntegrationAuthorizer(true);
		$methodAuthorizer = new ToggleMethodAuthorizationIntegrationAuthorizer(true);
		$dispatcher = $this->createDispatcher(
			controller: $controller,
			controllerClass: DualAuthorizationIntegrationController::class,
			authorizers: [
				ToggleControllerAuthorizationIntegrationAuthorizer::class => $controllerAuthorizer,
				ToggleMethodAuthorizationIntegrationAuthorizer::class => $methodAuthorizer,
			],
		);

		$result = $dispatcher->dispatch(
			new ApiRequest(method: 'GET', uri: '/secure-dual'),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"ok":true}', $result->getBody());
		self::assertSame(1, $controller->calls);
		self::assertSame(1, $controllerAuthorizer->calls);
		self::assertSame(1, $methodAuthorizer->calls);
	}

	#[Test]
	public function requestWithControllerAndMethodAuthorizersFailsWhenAnyAuthorizationDenies(): void
	{
		$controller = new DualAuthorizationIntegrationController();
		$controllerAuthorizer = new ToggleControllerAuthorizationIntegrationAuthorizer(true);
		$methodAuthorizer = new ToggleMethodAuthorizationIntegrationAuthorizer(false);
		$dispatcher = $this->createDispatcher(
			controller: $controller,
			controllerClass: DualAuthorizationIntegrationController::class,
			authorizers: [
				ToggleControllerAuthorizationIntegrationAuthorizer::class => $controllerAuthorizer,
				ToggleMethodAuthorizationIntegrationAuthorizer::class => $methodAuthorizer,
			],
		);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(403);
		$this->expectExceptionMessage('secure.method');

		try {
			$dispatcher->dispatch(
				new ApiRequest(method: 'GET', uri: '/secure-dual'),
				new ApiResponse(),
			);
		} finally {
			self::assertSame(0, $controller->calls);
			self::assertSame(1, $controllerAuthorizer->calls);
			self::assertSame(1, $methodAuthorizer->calls);
		}
	}

	/**
	 * @param class-string<Controller> $controllerClass
	 * @param array<class-string<Authorizer>, Authorizer> $authorizers
	 */
	private function createDispatcher(
		Controller $controller,
		string $controllerClass,
		array $authorizers,
	): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('authorization.controller')
			->setType($controllerClass);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$controllerContainer = $this->createMock(Container::class);
		$controllerContainer->method('getByType')
			->willReturnCallback(
				static fn (string $type, bool $throw = true): object|null => $type === $controllerClass ? $controller : null,
			);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));
		$handler = new ServiceHandler($controllerContainer, $serializer);

		$authorizerContainer = $this->createMock(Container::class);
		$authorizerContainer->method('getByType')
			->willReturnCallback(
				static fn (string $type, bool $throw = true): object|null => $authorizers[$type] ?? null,
			);
		$authorizationChecker = new AuthorizationChecker($authorizerContainer);

		return new ApiDispatcher(
			$router,
			$handler,
			$serializer,
			new RequestParameterMapping(),
			null,
			$authorizationChecker,
		);
	}

}

final class AuthorizationIntegrationController implements Controller
{

	public int $calls = 0;

	/** @return array{ok: true} */
	#[Get(path: '/secure')]
	#[Authorize(activity: 'secure.read', authorizer: ToggleAuthorizationIntegrationAuthorizer::class)]
	public function secure(): array
	{
		$this->calls++;

		return ['ok' => true];
	}

}

#[Authorize(activity: 'secure.controller', authorizer: ToggleControllerAuthorizationIntegrationAuthorizer::class)]
final class DualAuthorizationIntegrationController implements Controller
{

	public int $calls = 0;

	/** @return array{ok: true} */
	#[Get(path: '/secure-dual')]
	#[Authorize(activity: 'secure.method', authorizer: ToggleMethodAuthorizationIntegrationAuthorizer::class)]
	public function secureDual(): array
	{
		$this->calls++;

		return ['ok' => true];
	}

}

final class ToggleAuthorizationIntegrationAuthorizer implements Authorizer
{

	public function __construct(private bool $allowed)
	{
	}

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return $this->allowed;
	}

}

final class ToggleControllerAuthorizationIntegrationAuthorizer implements Authorizer
{

	public int $calls = 0;

	public function __construct(private bool $allowed)
	{
	}

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		$this->calls++;

		return $this->allowed;
	}

}

final class ToggleMethodAuthorizationIntegrationAuthorizer implements Authorizer
{

	public int $calls = 0;

	public function __construct(private bool $allowed)
	{
	}

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		$this->calls++;

		return $this->allowed;
	}

}
