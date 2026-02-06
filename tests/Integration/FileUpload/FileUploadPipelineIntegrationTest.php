<?php declare(strict_types = 1);

namespace Tests\Integration\FileUpload;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function count;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_OK;

final class FileUploadPipelineIntegrationTest extends TestCase
{

	#[Test]
	public function requiredSingleFileMissingReturns400(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = new ApiRequest(method: 'POST', uri: '/uploads/avatar');
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage("Required file 'avatar' is missing");

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function optionalSingleFileCanBeMissing(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = new ApiRequest(method: 'POST', uri: '/uploads/optional');
		$response = new ApiResponse();

		$result = $dispatcher->dispatch($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"present":false}', $result->getBody());
	}

	#[Test]
	public function validPlainTextUploadPassesAndControllerReceivesFile(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'integration-file-content';
		$tmpFile = $this->createTempFile('upload_doc_', $content);

		$uploadedFile = new UploadedFile(
			'document.txt',
			'text/plain',
			strlen($content),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/uploads/document'))
			->withUploadedFiles(['document' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());
			self::assertSame('application/json', $result->getHeader('content-type'));
			self::assertJson($result->getBody());

			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('document.txt', $payload['uploaded'] ?? null);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function spoofedMimeTypeIsRejectedByServerSideDetection(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'this is plain text, not a png image';
		$tmpFile = $this->createTempFile('upload_spoof_', $content);

		$uploadedFile = new UploadedFile(
			'avatar.png',
			'image/png',
			strlen($content),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/uploads/avatar'))
			->withUploadedFiles(['avatar' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(415);
			$this->expectExceptionMessage('File');

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function zeroByteFileIsRejected(): void
	{
		$dispatcher = $this->createDispatcher();
		$tmpFile = $this->createTempFile('upload_empty_', '');

		$uploadedFile = new UploadedFile(
			'document.txt',
			'text/plain',
			0,
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/uploads/document'))
			->withUploadedFiles(['document' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(400);
			$this->expectExceptionMessage('is empty');

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function mixedMultipleFilesAreRejectedWhenAnyMimeTypeIsInvalid(): void
	{
		$dispatcher = $this->createDispatcher();
		$validText = $this->createTempFile('upload_multi_ok_', 'valid text file');
		$invalidPng = $this->createTempFile('upload_multi_bad_', "\x89PNG\r\n\x1a\n");

		$uploadedFiles = [
			new UploadedFile('doc1.txt', 'text/plain', 15, $validText, UPLOAD_ERR_OK),
			new UploadedFile('doc2.png', 'image/png', 8, $invalidPng, UPLOAD_ERR_OK),
		];

		$request = (new ApiRequest(method: 'POST', uri: '/uploads/documents'))
			->withUploadedFiles(['documents' => $uploadedFiles]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(415);

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($validText);
			@unlink($invalidPng);
		}
	}

	private function createDispatcher(): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('fileUpload.controller')
			->setType(FileUploadPipelineController::class);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(FileUploadPipelineController::class)
			->willReturn(new FileUploadPipelineController());

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));

		$handler = new ServiceHandler($container, $serializer);
		$parameterMapping = new RequestParameterMapping();

		return new ApiDispatcher($router, $handler, $serializer, $parameterMapping);
	}

	private function createTempFile(string $prefix, string $contents): string
	{
		$tmpFile = tempnam(sys_get_temp_dir(), $prefix);
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, $contents);

		return $tmpFile;
	}

}

class FileUploadPipelineController implements Controller
{

	#[Post(path: '/uploads/avatar')]
	#[FileUpload(name: 'avatar', allowedTypes: ['image/png'], required: true)]
	public function uploadAvatar(ApiRequest $request): array
	{
		$file = $request->getUploadedFile('avatar');

		return [
			'uploaded' => $file?->getName(),
		];
	}

	#[Post(path: '/uploads/document')]
	#[FileUpload(name: 'document', allowedTypes: ['text/plain'], required: true)]
	public function uploadDocument(ApiRequest $request): array
	{
		$file = $request->getUploadedFile('document');

		return [
			'uploaded' => $file?->getName(),
		];
	}

	#[Post(path: '/uploads/optional')]
	#[FileUpload(name: 'optional', allowedTypes: ['text/plain'], required: false)]
	public function uploadOptional(ApiRequest $request): array
	{
		return [
			'present' => $request->getUploadedFile('optional') !== null,
		];
	}

	#[Post(path: '/uploads/documents')]
	#[FileUpload(name: 'documents', multiple: true, allowedTypes: ['text/plain'], required: true)]
	public function uploadDocuments(ApiRequest $request): array
	{
		return [
			'count' => count($request->getUploadedFiles('documents')),
		];
	}

}
