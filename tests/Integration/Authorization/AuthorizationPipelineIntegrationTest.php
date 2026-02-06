<?php declare(strict_types = 1);

namespace Tests\Integration\Authorization;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
			authorizer: new ToggleAuthorizationIntegrationAuthorizer(true),
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
			authorizer: new ToggleAuthorizationIntegrationAuthorizer(false),
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

	private function createDispatcher(
		AuthorizationIntegrationController $controller,
		ToggleAuthorizationIntegrationAuthorizer $authorizer,
	): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('authorization.controller')
			->setType(AuthorizationIntegrationController::class);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$controllerContainer = $this->createMock(Container::class);
		$controllerContainer->method('getByType')
			->with(AuthorizationIntegrationController::class)
			->willReturn($controller);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));
		$handler = new ServiceHandler($controllerContainer, $serializer);

		$authorizerContainer = $this->createMock(Container::class);
		$authorizerContainer->method('getByType')
			->willReturnCallback(
				static function (string $type, bool $throw = true) use ($authorizer): object|null {
					return $type === ToggleAuthorizationIntegrationAuthorizer::class ? $authorizer : null;
				},
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
