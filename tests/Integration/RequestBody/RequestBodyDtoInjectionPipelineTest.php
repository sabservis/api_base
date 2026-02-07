<?php declare(strict_types = 1);

namespace Tests\Integration\RequestBody;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\MapperOptions;
use Pocta\DataMapper\Validation\NotBlank;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Put;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use TypeError;
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
		$dispatcher = $this->createDefaultDispatcher();

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
		$dispatcher = $this->createDefaultDispatcher();

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

	#[Test]
	public function putInferredRequestBodyRejectsWrongContentType(): void
	{
		$dispatcher = $this->createDefaultDispatcher();

		$request = new ApiRequest(
			method: 'PUT',
			uri: '/users/42',
			headers: ['Content-Type' => 'text/plain'],
			body: '{"name":"Updated Name"}',
		);
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(415);
		$this->expectExceptionMessage('Unsupported Content-Type');

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function putInferredRequiredRequestBodyRejectsEmptyBody(): void
	{
		$dispatcher = $this->createDefaultDispatcher();

		$request = new ApiRequest(method: 'PUT', uri: '/users/42');
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage('Request body is empty');

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function putInferredRequiredRequestBodyRejectsMissingContentTypeWhenBodyPresent(): void
	{
		$dispatcher = $this->createDefaultDispatcher();

		$request = new ApiRequest(
			method: 'PUT',
			uri: '/users/42',
			body: '{"name":"Updated Name"}',
		);
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(415);
		$this->expectExceptionMessage('Unsupported Content-Type');

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function postInferredRequestBodyReturnsValidationError422(): void
	{
		$dispatcher = $this->createValidatedDispatcher();

		$request = new ApiRequest(
			method: 'POST',
			uri: '/validated-users',
			headers: ['Content-Type' => 'application/json'],
			body: '{}',
		);
		$response = new ApiResponse();

		$this->expectException(ValidationException::class);
		$this->expectExceptionCode(422);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function optionalInferredRequestBodyAllowsEmptyBodyWhenNotRequired(): void
	{
		$dispatcher = $this->createDefaultDispatcher();

		$request = new ApiRequest(method: 'PUT', uri: '/users/42/optional');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('application/json', $result->getHeader('content-type'));
		self::assertSame('{"id":42,"name":null}', $result->getBody());
	}

	#[Test]
	public function getWithDtoParameterAndProvidedQueryTriggersTypeError(): void
	{
		$dispatcher = $this->createDefaultDispatcher();

		$request = new ApiRequest(
			method: 'GET',
			uri: '/users/search?input=john',
			queryParams: ['input' => 'john'],
		);
		$response = new ApiResponse();

		$this->expectException(TypeError::class);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function getWithDtoParameterDoesNotInferRequestBodyAndUsesQueryValidation(): void
	{
		$dispatcher = $this->createDefaultDispatcher();

		$request = new ApiRequest(method: 'GET', uri: '/users/search');
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage('Query request parameter "input" should be provided.');

		$dispatcher->dispatch($request, $response);
	}

	private function createDefaultDispatcher(): ApiDispatcher
	{
		return $this->createDispatcherForController(
			RequestBodyPipelineController::class,
			new RequestBodyPipelineController(),
			$this->createMockSerializer(),
		);
	}

	private function createValidatedDispatcher(): ApiDispatcher
	{
		return $this->createDispatcherForController(
			RequestBodyValidationController::class,
			new RequestBodyValidationController(),
			new DataMapperSerializer(MapperOptions::withAutoValidation()),
		);
	}

	private function createDispatcherForController(
		string $controllerClass,
		Controller $controller,
		EntitySerializer $serializer,
	): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('requestBody.controller')
			->setType($controllerClass);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with($controllerClass)
			->willReturn($controller);

		$handler = new ServiceHandler($container, $serializer);
		$parameterMapping = new RequestParameterMapping();

		return new ApiDispatcher($router, $handler, $serializer, $parameterMapping);
	}

	private function createMockSerializer(): EntitySerializer
	{
		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')
			->willReturnCallback(static function (mixed $data, mixed $type): object {
				$payload = json_decode((string) $data, true);

				assert(is_string($type));
				assert($payload !== null);

				if ($type === CreateUserDto::class) {
					$dto = new CreateUserDto();
					$dto->name = $payload['name'] ?? '';

					return $dto;
				}

				if ($type === UpdateUserDto::class) {
					$dto = new UpdateUserDto();
					$dto->name = $payload['name'] ?? '';

					return $dto;
				}

				$dto = new OptionalUpdateUserDto();
				$dto->name = $payload['name'] ?? '';

				return $dto;
			});
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));

		return $serializer;
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

	#[Put(path: '/users/{id}/optional')]
	public function optionalUpdate(int $id, OptionalUpdateUserDto|null $input = null): array
	{
		return [
			'id' => $id,
			'name' => $input?->name,
		];
	}

	#[Get(path: '/users/search')]
	public function search(SearchQueryDto $input): array
	{
		return [
			'query' => $input->query,
		];
	}

}

class RequestBodyValidationController implements Controller
{

	#[Post(path: '/validated-users')]
	public function create(ValidatedCreateUserDto $input): array
	{
		return [
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

class OptionalUpdateUserDto
{

	public string $name;

}

class SearchQueryDto
{

	public string $query;

}

class ValidatedCreateUserDto
{

	#[NotBlank]
	public string|null $name = null;

}
