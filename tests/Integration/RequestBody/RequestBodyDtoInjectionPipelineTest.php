<?php declare(strict_types = 1);

namespace Tests\Integration\RequestBody;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Put;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function assert;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class RequestBodyDtoInjectionPipelineTest extends TestCase
{

	#[Test]
	public function postWithRequestBodyAttributeInjectsDtoFromJsonBody(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'application/json'],
			body: '{"name":"John"}',
		);
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('application/json', $result->getHeader('content-type'));
		self::assertSame('{"name":"John"}', $result->getBody());
	}

	#[Test]
	public function putWithPathParameterAndDtoInjectsBothArguments(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = new ApiRequest(
			method: 'PUT',
			uri: '/users/42',
			headers: ['Content-Type' => 'application/json'],
			body: '{"name":"Updated Name"}',
		);
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('application/json', $result->getHeader('content-type'));
		self::assertSame('{"id":42,"name":"Updated Name"}', $result->getBody());
	}

	private function createDispatcher(): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('requestBody.controller')
			->setType(RequestBodyPipelineController::class);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$controller = new RequestBodyPipelineController();
		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(RequestBodyPipelineController::class)
			->willReturn($controller);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')
			->willReturnCallback(static function (mixed $data, mixed $type): object {
				$payload = json_decode((string) $data, true);

				assert(is_string($type));
				assert($payload !== null);

				if ($type === CreateUserDto::class) {
					$dto = new CreateUserDto();
					$dto->name = $payload['name'];

					return $dto;
				}

				$dto = new UpdateUserDto();
				$dto->name = $payload['name'];

				return $dto;
			});
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));

		$handler = new ServiceHandler($container, $serializer);
		$parameterMapping = new RequestParameterMapping();

		return new ApiDispatcher($router, $handler, $serializer, $parameterMapping);
	}

}

class RequestBodyPipelineController implements Controller
{

	#[Post(path: '/users')]
	#[RequestBody(ref: CreateUserDto::class, required: true)]
	public function create(CreateUserDto $input): array
	{
		return [
			'name' => $input->name,
		];
	}

	#[Put(path: '/users/{id}')]
	public function update(int $id, UpdateUserDto $input): array
	{
		return [
			'id' => $id,
			'name' => $input->name,
		];
	}

}

class CreateUserDto
{

	public string $name;

}

class UpdateUserDto
{

	public string $name;

}
