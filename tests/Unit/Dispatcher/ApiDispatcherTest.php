<?php declare(strict_types = 1);

namespace Tests\Unit\Dispatcher;

use Nette\DI\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\Runtime\EarlyReturnResponseException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointAuthorization;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Security\AuthorizationChecker;
use Sabservis\Api\Security\Authorizer;
use stdClass;
use function file_put_contents;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const UPLOAD_ERR_OK;

final class ApiDispatcherTest extends TestCase
{

	private function createRouter(Endpoint ...$endpoints): Router
	{
		$schema = new Schema();

		foreach ($endpoints as $endpoint) {
			$schema->addEndpoint($endpoint);
		}

		return new Router($schema);
	}

	private function createEndpoint(string $method, string $mask, string $controllerMethod = 'handle'): Endpoint
	{
		$endpoint = new Endpoint('TestController', $controllerMethod);
		$endpoint->addMethod($method);
		$endpoint->setMask($mask);

		// For dynamic routes with parameters, set pattern
		if (str_contains($mask, '{')) {
			$pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $mask);
			$endpoint->setPattern('#^' . $pattern . '$#');

			// Extract parameter names and add them
			preg_match_all('/\{(\w+)\}/', $mask, $matches);

			foreach ($matches[1] as $paramName) {
				$param = new EndpointParameter($paramName);
				$param->setIn(EndpointParameter::InPath);
				$endpoint->addParameter($param);
			}
		}

		return $endpoint;
	}

	#[Test]
	public function dispatchNotFoundThrowsException(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users');
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/not-found');
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Not found');
		$this->expectExceptionCode(404);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchSuccessfulRoute(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users');
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['users' => []]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"users":[]}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/users');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('application/json', $result->getHeader('content-type'));
		self::assertSame('{"users":[]}', $result->getBody());
	}

	#[Test]
	public function dispatchWithRequestBodyDeserialization(): void
	{
		$endpoint = $this->createEndpoint('POST', '/users', 'createUser');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$endpoint->setRequestBody($requestBody);

		$router = $this->createRouter($endpoint);

		$dto = new TestCreateUserDto();
		$dto->name = 'John';

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['created' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')->willReturn($dto);
		$serializer->method('serialize')->willReturn('{"created":true}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'application/json'],
			body: '{"name":"John"}',
		);
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function dispatchWithValidation(): void
	{
		$endpoint = $this->createEndpoint('POST', '/users', 'createUser');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$endpoint->setRequestBody($requestBody);

		$router = $this->createRouter($endpoint);

		$dto = new TestCreateUserDto();

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['created' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')->willReturn($dto);
		$serializer->method('serialize')->willReturn('{"created":true}');

		$parameterMapping = new RequestParameterMapping();

		$validator = $this->createMock(EntityValidator::class);
		$validator->expects($this->once())->method('validate')->with($dto);

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping, $validator);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'application/json'],
			body: '{"name":"John"}',
		);
		$response = new ApiResponse();

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchInvokesAuthorizationCheckerBeforeHandler(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users');
		$endpoint->addAuthorization(
			new EndpointAuthorization('users.read', AllowAllDispatcherAuthorizer::class),
		);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->expects($this->once())->method('handle')->willReturn(['ok' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"ok":true}');
		$parameterMapping = new RequestParameterMapping();

		$authContainer = $this->createMock(Container::class);
		$authContainer->method('getByType')
			->with(AllowAllDispatcherAuthorizer::class, false)
			->willReturn(new AllowAllDispatcherAuthorizer());

		$authorizationChecker = new AuthorizationChecker($authContainer);
		$dispatcher = new ApiDispatcher(
			$router,
			$handler,
			$serializer,
			$parameterMapping,
			null,
			$authorizationChecker,
		);

		$result = $dispatcher->dispatch(new ApiRequest(method: 'GET', uri: '/users'), new ApiResponse());

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function dispatchStopsBeforeHandlerWhenAuthorizationDenied(): void
	{
		$endpoint = $this->createEndpoint('DELETE', '/users/1');
		$endpoint->addAuthorization(
			new EndpointAuthorization('users.delete', DenyAllDispatcherAuthorizer::class),
		);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->expects($this->never())->method('handle');

		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$authContainer = $this->createMock(Container::class);
		$authContainer->method('getByType')
			->with(DenyAllDispatcherAuthorizer::class, false)
			->willReturn(new DenyAllDispatcherAuthorizer());

		$authorizationChecker = new AuthorizationChecker($authContainer);
		$dispatcher = new ApiDispatcher(
			$router,
			$handler,
			$serializer,
			$parameterMapping,
			null,
			$authorizationChecker,
		);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(403);
		$this->expectExceptionMessage('users.delete');

		$dispatcher->dispatch(new ApiRequest(method: 'DELETE', uri: '/users/1'), new ApiResponse());
	}

	#[Test]
	public function dispatchLetsClientExceptionBubbleUp(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users/{id}', 'getUser');
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willThrowException(
			new ClientErrorException('User not found', 404),
		);

		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/users/1');
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('User not found');
		$this->expectExceptionCode(404);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchLetsServerExceptionBubbleUp(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users/{id}', 'getUser');
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willThrowException(
			new ServerErrorException('Database error', 500),
		);

		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/users/1');
		$response = new ApiResponse();

		$this->expectException(ServerErrorException::class);
		$this->expectExceptionMessage('Database error');
		$this->expectExceptionCode(500);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchLetsClientExceptionWithContextBubbleUp(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users/{id}', 'getUser');
		$router = $this->createRouter($endpoint);

		$exception = new ClientErrorException('Validation failed', 422);
		$exception = $exception->withContext(['field' => 'email', 'error' => 'invalid format']);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willThrowException($exception);

		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/users/1');
		$response = new ApiResponse();

		try {
			$dispatcher->dispatch($request, $response);
			self::fail('Expected ClientErrorException to be thrown');
		} catch (ClientErrorException $e) {
			self::assertSame(422, $e->getCode());
			self::assertSame(['field' => 'email', 'error' => 'invalid format'], $e->getContext());
		}
	}

	#[Test]
	public function dispatchHandlesEarlyReturnResponse(): void
	{
		$endpoint = $this->createEndpoint('GET', '/old-path', 'redirect');
		$router = $this->createRouter($endpoint);

		$earlyResponse = (new ApiResponse())
			->withStatus(302)
			->withHeader('Location', '/new-location');

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willThrowException(
			new EarlyReturnResponseException($earlyResponse),
		);

		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/old-path');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(302, $result->getStatusCode());
		self::assertSame('/new-location', $result->getHeader('location'));
	}

	#[Test]
	public function dispatchHandlerReturnsApiResponse(): void
	{
		$endpoint = $this->createEndpoint('POST', '/items', 'create');
		$router = $this->createRouter($endpoint);

		$responseObject = new stdClass();
		$responseObject->id = 1;
		$responseObject->name = 'Created';

		$handlerResponse = (new ApiResponse())
			->withStatus(201)
			->withObject($responseObject);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn($handlerResponse);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')
			->with($responseObject)
			->willReturn('{"id":1,"name":"Created"}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'POST', uri: '/items');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(201, $result->getStatusCode());
		self::assertSame('{"id":1,"name":"Created"}', $result->getBody());
	}

	#[Test]
	public function dispatchPreservesHeadersFromApiResponseWithObject(): void
	{
		$endpoint = $this->createEndpoint('POST', '/items', 'create');
		$router = $this->createRouter($endpoint);

		$responseObject = new stdClass();
		$responseObject->id = 123;

		// Controller returns ApiResponse with headers AND object
		$handlerResponse = (new ApiResponse())
			->withStatus(201)
			->withHeader('Location', '/items/123')
			->withHeader('X-Custom-Header', 'custom-value')
			->withObject($responseObject);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn($handlerResponse);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"id":123}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'POST', uri: '/items');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		// Headers from controller response must be preserved
		self::assertSame(201, $result->getStatusCode());
		self::assertSame('/items/123', $result->getHeader('location'));
		self::assertSame('custom-value', $result->getHeader('x-custom-header'));
		self::assertSame('{"id":123}', $result->getBody());
	}

	#[Test]
	public function dispatchHandlerReturnsApiResponseWithoutObject(): void
	{
		$endpoint = $this->createEndpoint('DELETE', '/items/{id}', 'delete');
		$router = $this->createRouter($endpoint);

		$handlerResponse = (new ApiResponse())->withStatus(204);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn($handlerResponse);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->expects($this->never())->method('serialize');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'DELETE', uri: '/items/1');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(204, $result->getStatusCode());
	}

	#[Test]
	public function dispatchWithoutRequestBodySkipsDeserialization(): void
	{
		$endpoint = $this->createEndpoint('GET', '/users');
		// No request body set
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['users' => []]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->expects($this->never())->method('deserialize');
		$serializer->method('serialize')->willReturn('{"users":[]}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/users');
		$response = new ApiResponse();

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchExceptionPreservesCodeWhenBubblingUp(): void
	{
		$endpoint = $this->createEndpoint('GET', '/error');
		$router = $this->createRouter($endpoint);

		// Exception with code but minimal message
		$exception = new ClientErrorException('Bad request', 400);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willThrowException($exception);

		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/error');
		$response = new ApiResponse();

		try {
			$dispatcher->dispatch($request, $response);
			self::fail('Expected ClientErrorException to be thrown');
		} catch (ClientErrorException $e) {
			self::assertSame(400, $e->getCode());
			self::assertSame('Bad request', $e->getMessage());
		}
	}

	// === File Upload Validation Tests ===

	private function createEndpointWithFileUpload(
		string $method,
		string $mask,
		FileUpload $fileUpload,
	): Endpoint
	{
		$endpoint = $this->createEndpoint($method, $mask, 'uploadFile');

		$requestBody = new EndpointRequestBody();
		$requestBody->setFileUploads([$fileUpload]);
		$endpoint->setRequestBody($requestBody);

		return $endpoint;
	}

	#[Test]
	public function dispatchValidatesRequiredFileUpload(): void
	{
		$fileUpload = new FileUpload(name: 'avatar', required: true);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Request without file
		$request = new ApiRequest(method: 'POST', uri: '/upload');
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Required file 'avatar' is missing");
		$this->expectExceptionCode(400);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchAllowsOptionalMissingFile(): void
	{
		$fileUpload = new FileUpload(
			name: 'avatar',
			required: false, // Optional
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['success' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"success":true}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Request without file - should be OK since it's optional
		$request = new ApiRequest(method: 'POST', uri: '/upload');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function dispatchValidatesFileMimeType(): void
	{
		$fileUpload = new FileUpload(
			name: 'avatar',
			required: true,
			allowedTypes: ['image/jpeg', 'image/png'],
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Create a text file (not an image)
		$tmpFile = tempnam(sys_get_temp_dir(), 'upload_test');
		file_put_contents($tmpFile, 'This is plain text, not an image');

		$uploadedFile = new UploadedFile(
			'avatar.jpg', // Fake extension
			'image/jpeg', // Fake MIME from client
			33,
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/upload'))
			->withUploadedFiles(['avatar' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(415); // Unsupported Media Type

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function dispatchAcceptsValidMimeType(): void
	{
		$fileUpload = new FileUpload(
			name: 'document',
			required: true,
			allowedTypes: ['text/plain'],
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['uploaded' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"uploaded":true}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Create a real text file
		$tmpFile = tempnam(sys_get_temp_dir(), 'upload_valid');
		file_put_contents($tmpFile, 'This is plain text content');

		$uploadedFile = new UploadedFile(
			'document.txt',
			'text/plain',
			26,
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/upload'))
			->withUploadedFiles(['document' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);
			self::assertSame(200, $result->getStatusCode());
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function dispatchAcceptsFileWithNoTypeRestriction(): void
	{
		$fileUpload = new FileUpload(
			name: 'anyfile',
			required: true,
			allowedTypes: null, // No restrictions
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['uploaded' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"uploaded":true}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$tmpFile = tempnam(sys_get_temp_dir(), 'upload_any');
		file_put_contents($tmpFile, 'Any content');

		$uploadedFile = new UploadedFile('file.bin', 'application/octet-stream', 11, $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/upload'))
			->withUploadedFiles(['anyfile' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);
			self::assertSame(200, $result->getStatusCode());
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function dispatchValidatesMultipleFileUploads(): void
	{
		$fileUpload = new FileUpload(
			name: 'documents',
			multiple: true,
			required: true,
			allowedTypes: ['text/plain'],
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload-multi', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Create files - one valid, one invalid
		$validFile = tempnam(sys_get_temp_dir(), 'valid');
		file_put_contents($validFile, 'Valid text');

		$invalidFile = tempnam(sys_get_temp_dir(), 'invalid');
		// Write minimal PNG header to make it detected as image/png
		file_put_contents($invalidFile, "\x89PNG\r\n\x1a\n");

		$uploadedFiles = [
			new UploadedFile('doc1.txt', 'text/plain', 10, $validFile, UPLOAD_ERR_OK),
			new UploadedFile('doc2.png', 'image/png', 8, $invalidFile, UPLOAD_ERR_OK),
		];

		$request = (new ApiRequest(method: 'POST', uri: '/upload-multi'))
			->withUploadedFiles(['documents' => $uploadedFiles]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(415);

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($validFile);
			@unlink($invalidFile);
		}
	}

	// === X-Content-Type-Options Tests ===

	#[Test]
	public function jsonResponseIncludesNosniffHeader(): void
	{
		$endpoint = $this->createEndpoint('GET', '/data');
		$router = $this->createRouter($endpoint);

		$resultData = new stdClass();
		$resultData->name = 'test';

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn($resultData);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')->willReturn('{"name":"test"}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(method: 'GET', uri: '/data');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame('nosniff', $result->getHeader('x-content-type-options'));
	}

	#[Test]
	public function dispatchRejectsZeroByteFileUpload(): void
	{
		$fileUpload = new FileUpload(
			name: 'document',
			required: true,
			allowedTypes: ['text/plain'],
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Create a 0-byte file
		$tmpFile = tempnam(sys_get_temp_dir(), 'empty');
		file_put_contents($tmpFile, ''); // Empty file

		$uploadedFile = new UploadedFile(
			'empty.txt',
			'text/plain',
			0, // 0 bytes
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/upload'))
			->withUploadedFiles(['document' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionMessage("File 'document' is empty (0 bytes)");
			$this->expectExceptionCode(400);

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function dispatchRejectsZeroByteFileInMultipleUpload(): void
	{
		$fileUpload = new FileUpload(
			name: 'documents',
			multiple: true,
			required: true,
			allowedTypes: ['text/plain'],
		);

		$endpoint = $this->createEndpointWithFileUpload('POST', '/upload-multi', $fileUpload);
		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Create files - one valid, one empty
		$validFile = tempnam(sys_get_temp_dir(), 'valid');
		file_put_contents($validFile, 'Valid text content');

		$emptyFile = tempnam(sys_get_temp_dir(), 'empty');
		file_put_contents($emptyFile, ''); // Empty

		$uploadedFiles = [
			new UploadedFile('doc1.txt', 'text/plain', 18, $validFile, UPLOAD_ERR_OK),
			new UploadedFile('doc2.txt', 'text/plain', 0, $emptyFile, UPLOAD_ERR_OK),
		];

		$request = (new ApiRequest(method: 'POST', uri: '/upload-multi'))
			->withUploadedFiles(['documents' => $uploadedFiles]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(400);

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($validFile);
			@unlink($emptyFile);
		}
	}

	// === Content-Type Validation Tests ===

	#[Test]
	public function dispatchRejectsWrongContentTypeForJsonBody(): void
	{
		$endpoint = $this->createEndpoint('POST', '/users', 'createUser');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$endpoint->setRequestBody($requestBody);

		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Send text/plain instead of application/json
		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'text/plain'],
			body: '{"name":"John"}',
		);
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Unsupported Content-Type');
		$this->expectExceptionCode(415);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchRejectsMissingContentTypeForJsonBody(): void
	{
		$endpoint = $this->createEndpoint('POST', '/users', 'createUser');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$endpoint->setRequestBody($requestBody);

		$router = $this->createRouter($endpoint);

		$handler = $this->createMock(ServiceHandler::class);
		$serializer = $this->createMock(EntitySerializer::class);
		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// No Content-Type header
		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			body: '{"name":"John"}',
		);
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(415);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function dispatchAcceptsJsonContentTypeWithCharset(): void
	{
		$endpoint = $this->createEndpoint('POST', '/users', 'createUser');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$endpoint->setRequestBody($requestBody);

		$router = $this->createRouter($endpoint);

		$dto = new TestCreateUserDto();
		$dto->name = 'John';

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['created' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')->willReturn($dto);
		$serializer->method('serialize')->willReturn('{"created":true}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		// Content-Type with charset parameter
		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'application/json; charset=utf-8'],
			body: '{"name":"John"}',
		);
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	#[Test]
	public function dispatchAcceptsCustomAllowedContentTypes(): void
	{
		$endpoint = $this->createEndpoint('POST', '/users', 'createUser');
		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity(TestCreateUserDto::class);
		$requestBody->setAllowedContentTypes(['application/json', 'application/x-www-form-urlencoded']);
		$endpoint->setRequestBody($requestBody);

		$router = $this->createRouter($endpoint);

		$dto = new TestCreateUserDto();
		$dto->name = 'John';

		$handler = $this->createMock(ServiceHandler::class);
		$handler->method('handle')->willReturn(['created' => true]);

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('deserialize')->willReturn($dto);
		$serializer->method('serialize')->willReturn('{"created":true}');

		$parameterMapping = new RequestParameterMapping();

		$dispatcher = new ApiDispatcher($router, $handler, $serializer, $parameterMapping);

		$request = new ApiRequest(
			method: 'POST',
			uri: '/users',
			headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
			body: 'name=John',
		);
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

}

class TestCreateUserDto
{

	public string $name = '';

}

final class AllowAllDispatcherAuthorizer implements Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return true;
	}

}

final class DenyAllDispatcherAuthorizer implements Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return false;
	}

}
