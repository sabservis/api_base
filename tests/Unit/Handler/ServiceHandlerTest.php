<?php declare(strict_types = 1);

namespace Tests\Unit\Handler;

use Nette\DI\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\UI\Controller\Controller;

final class ServiceHandlerTest extends TestCase
{

	#[Test]
	public function handleCallsControllerMethod(): void
	{
		$controller = new TestController();
		$endpoint = new Endpoint(TestController::class, 'getUsers');

		$container = $this->createMock(Container::class);
		$container->expects($this->once())
			->method('getByType')
			->with(TestController::class)
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		$request = new ApiRequest(method: 'GET', uri: '/users');
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame(['users' => []], $result);
	}

	#[Test]
	public function handlePassesRequestAndResponseToController(): void
	{
		$controller = new TestController();
		$endpoint = new Endpoint(TestController::class, 'echo');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		$request = new ApiRequest(method: 'POST', uri: '/echo');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute('test-data', 'hello');
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame('hello', $result['echo']);
	}

	#[Test]
	public function handleReturnsApiResponse(): void
	{
		$controller = new TestController();
		$endpoint = new Endpoint(TestController::class, 'returnResponse');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertInstanceOf(ApiResponse::class, $result);
		self::assertSame(201, $result->getStatusCode());
	}

	#[Test]
	public function handleThrowsWhenServiceNotController(): void
	{
		$notController = new NotAController();
		$endpoint = new Endpoint(NotAController::class, 'doSomething');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($notController);

		$handler = new ServiceHandler($container);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$response = new ApiResponse();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must implement');

		$handler->handle($request, $response);
	}

	#[Test]
	public function handleWithDifferentMethods(): void
	{
		$controller = new TestController();

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);
		$response = new ApiResponse();

		// Test getUser method
		$endpoint1 = new Endpoint(TestController::class, 'getUser');
		$request1 = (new ApiRequest(method: 'GET', uri: '/users/1'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint1)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => 1]);

		$result1 = $handler->handle($request1, $response);
		self::assertSame(1, $result1['id']);

		// Test createUser method
		$endpoint2 = new Endpoint(TestController::class, 'createUser');
		$request2 = (new ApiRequest(method: 'POST', uri: '/users'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint2);

		$result2 = $handler->handle($request2, $response);
		self::assertSame('created', $result2['status']);
	}

	#[Test]
	public function handleInjectsParametersDirectlyToMethod(): void
	{
		$controller = new InferredParamsController();
		$endpoint = new Endpoint(InferredParamsController::class, 'getUserById');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		$request = (new ApiRequest(method: 'GET', uri: '/users/42'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => 42]);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame(42, $result['id']);
	}

	#[Test]
	public function handleInjectsMultipleParameters(): void
	{
		$controller = new InferredParamsController();
		$endpoint = new Endpoint(InferredParamsController::class, 'listUsers');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		$request = (new ApiRequest(method: 'GET', uri: '/users'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['limit' => 10, 'offset' => 5]);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame(10, $result['limit']);
		self::assertSame(5, $result['offset']);
	}

	#[Test]
	public function handleUsesDefaultValueForOptionalParameter(): void
	{
		$controller = new InferredParamsController();
		$endpoint = new Endpoint(InferredParamsController::class, 'listUsers');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		// Only provide limit, offset should use default value
		$request = (new ApiRequest(method: 'GET', uri: '/users'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['limit' => 25]);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame(25, $result['limit']);
		self::assertSame(0, $result['offset']); // default value
	}

	#[Test]
	public function handleMixesInjectedParamsWithRequestResponse(): void
	{
		$controller = new InferredParamsController();
		$endpoint = new Endpoint(InferredParamsController::class, 'getUserWithRequest');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$handler = new ServiceHandler($container);

		$request = (new ApiRequest(method: 'GET', uri: '/users/99'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => 99])
			->withHeader('X-Custom', 'test-header');
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame(99, $result['id']);
		self::assertSame('test-header', $result['header']);
	}

	#[Test]
	public function handleInjectsDtoFromRequestBody(): void
	{
		$controller = new DtoController();
		$endpoint = new Endpoint(DtoController::class, 'createUser');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')
			->with('{"name":"John","email":"john@example.com"}', CreateUserDto::class)
			->willReturn(new CreateUserDto('John', 'john@example.com'));

		$handler = new ServiceHandler($container, $serializer);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			body: '{"name":"John","email":"john@example.com"}',
		);
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame('John', $result['name']);
		self::assertSame('john@example.com', $result['email']);
	}

	#[Test]
	public function handleInjectsDtoFromRequestAttribute(): void
	{
		$controller = new DtoController();
		$endpoint = new Endpoint(DtoController::class, 'createUser');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		// DTO already deserialized by ApiDispatcher
		$dto = new CreateUserDto('Jane', 'jane@example.com');

		$handler = new ServiceHandler($container);

		$request = (new ApiRequest(method: 'POST', uri: '/users'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::RequestEntity->value, $dto);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame('Jane', $result['name']);
		self::assertSame('jane@example.com', $result['email']);
	}

	#[Test]
	public function handleInjectsDtoWithOtherParameters(): void
	{
		$controller = new DtoController();
		$endpoint = new Endpoint(DtoController::class, 'updateUser');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$dto = new UpdateUserDto('Updated Name');

		$handler = new ServiceHandler($container);

		$request = (new ApiRequest(method: 'PUT', uri: '/users/42'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => 42])
			->withAttribute(RequestAttributes::RequestEntity->value, $dto);
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame(42, $result['id']);
		self::assertSame('Updated Name', $result['name']);
	}

	#[Test]
	public function handleInjectsDtoWithRequestAndResponse(): void
	{
		$controller = new DtoController();
		$endpoint = new Endpoint(DtoController::class, 'createUserWithRequest');

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn($controller);

		$dto = new CreateUserDto('John', 'john@example.com');

		$handler = new ServiceHandler($container);

		$request = (new ApiRequest(method: 'POST', uri: '/users'))
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::RequestEntity->value, $dto)
			->withHeader('X-Request-ID', 'req-123');
		$response = new ApiResponse();

		$result = $handler->handle($request, $response);

		self::assertSame('John', $result['name']);
		self::assertSame('req-123', $result['requestId']);
	}

}

/**
 * Controller with inferred parameter injection
 */
class InferredParamsController implements Controller
{

	public function getUserById(int $id): array
	{
		return ['id' => $id];
	}

	public function listUsers(int $limit = 20, int $offset = 0): array
	{
		return ['limit' => $limit, 'offset' => $offset];
	}

	public function getUserWithRequest(int $id, ApiRequest $request): array
	{
		return [
			'id' => $id,
			'header' => $request->getHeader('X-Custom'),
		];
	}

}

/**
 * Test controller implementing Controller interface
 */
class TestController implements Controller
{

	public function getUsers(ApiRequest $request, ApiResponse $response): array
	{
		return ['users' => []];
	}

	public function getUser(ApiRequest $request, ApiResponse $response): array
	{
		return ['id' => $request->getAttribute(RequestAttributes::Parameters->value)['id']];
	}

	public function createUser(ApiRequest $request, ApiResponse $response): array
	{
		return ['status' => 'created'];
	}

	public function echo(ApiRequest $request, ApiResponse $response): array
	{
		return ['echo' => $request->getAttribute('test-data')];
	}

	public function returnResponse(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response->withStatus(201);
	}

}

/**
 * Class that does NOT implement Controller - for testing error case
 */
class NotAController
{

	public function doSomething(): void
	{
		// Intentionally empty method for negative controller-type test.
	}

}

/**
 * Controller with DTO injection
 */
class DtoController implements Controller
{

	public function createUser(CreateUserDto $input): array
	{
		return [
			'name' => $input->name,
			'email' => $input->email,
		];
	}

	public function updateUser(int $id, UpdateUserDto $input): array
	{
		return [
			'id' => $id,
			'name' => $input->name,
		];
	}

	public function createUserWithRequest(CreateUserDto $input, ApiRequest $request): array
	{
		return [
			'name' => $input->name,
			'requestId' => $request->getHeader('X-Request-ID'),
		];
	}

}

/**
 * Test DTO for user creation
 */
class CreateUserDto
{

	public function __construct(public string $name, public string $email)
	{
	}

}

/**
 * Test DTO for user update
 */
class UpdateUserDto
{

	public function __construct(public string $name)
	{
	}

}
