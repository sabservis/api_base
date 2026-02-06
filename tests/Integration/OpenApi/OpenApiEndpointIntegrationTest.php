<?php declare(strict_types = 1);

namespace Tests\Integration\OpenApi;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\OpenApi\Controller\OpenApiController;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function is_array;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class OpenApiEndpointIntegrationTest extends TestCase
{

	#[Test]
	public function openApiJsonEndpointIsServedByRouterAndContainsLoadedPaths(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = new ApiRequest(method: 'GET', uri: '/openapi.json');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('application/json', $result->getHeader('content-type'));
		self::assertSame('public, max-age=3600', $result->getHeader('cache-control'));
		self::assertJson($result->getBody());

		$decoded = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertTrue(is_array($decoded));
		self::assertSame('Integration OpenAPI', $decoded['info']['title'] ?? null);
		self::assertSame('9.9.9', $decoded['info']['version'] ?? null);
		self::assertArrayHasKey('/openapi.json', $decoded['paths'] ?? []);
		self::assertArrayHasKey('/ping', $decoded['paths'] ?? []);
	}

	private function createDispatcher(): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('openapi.controller')
			->setType(OpenApiController::class);
		$schemaLoaderContainerBuilder->addDefinition('openapi.public.controller')
			->setType(OpenApiPublicController::class);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$openApiController = new OpenApiController(
			$schema,
			new OpenApiConfig(title: 'Integration OpenAPI', version: '9.9.9'),
		);
		$publicController = new OpenApiPublicController();

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturnCallback(
				static fn (string $type): Controller => match ($type) {
						OpenApiController::class => $openApiController,
						OpenApiPublicController::class => $publicController,
						default => throw new RuntimeException('Unknown controller type: ' . $type),
				},
			);

		$serializer = $this->createMock(EntitySerializer::class);
		$handler = new ServiceHandler($container, $serializer);
		$parameterMapping = new RequestParameterMapping();

		return new ApiDispatcher($router, $handler, $serializer, $parameterMapping);
	}

}

class OpenApiPublicController implements Controller
{

	#[Get(path: '/ping')]
	public function ping(): array
	{
		return ['pong' => true];
	}

}
