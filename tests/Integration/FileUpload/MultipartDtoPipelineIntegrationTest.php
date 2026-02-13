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
use Sabservis\Api\Mapping\MultipartEntityHydrator;
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

enum PipelineFormalityType: string
{

	case Invoice = 'invoice';

	case Receipt = 'receipt';

}

class PipelineFormalityDto
{

	public string $type;

	#[FileUpload(name: 'file', allowedTypes: ['application/pdf'], required: true)]
	public UploadedFile $file;

	#[FileUpload(name: 'thumbnail', allowedTypes: ['image/png'], required: false)]
	public UploadedFile|null $thumbnail = null;

}

class PipelineMultiFilesDto
{

	public string $label;

	/** @var array<UploadedFile> */
	#[FileUpload(name: 'attachments', multiple: true, allowedTypes: ['text/plain'], required: true)]
	public array $attachments;

}

class PipelineEnumDto
{

	public PipelineFormalityType $category;

	#[FileUpload(name: 'file', allowedTypes: ['text/plain'], required: true)]
	public UploadedFile $file;

}

final class MultipartDtoPipelineIntegrationTest extends TestCase
{

	#[Test]
	public function fullPipelineHydratesDtoWithFileAndFormField(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = "%PDF-1.4\n";
		$tmpFile = $this->createTempFile('pipeline_pdf_', $content);

		$uploadedFile = new UploadedFile(
			'invoice.pdf',
			'application/pdf',
			strlen($content),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/formalities'))
			->withParsedBody(['type' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());
			self::assertSame('application/json', $result->getHeader('content-type'));
			self::assertJson($result->getBody());

			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('invoice', $payload['type']);
			self::assertSame('invoice.pdf', $payload['fileName']);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function missingRequiredFileReturns400(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = (new ApiRequest(method: 'POST', uri: '/formalities'))
			->withParsedBody(['type' => 'invoice']);
		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage("Required file 'file' is missing");

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function invalidMimeTypeReturns415(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'this is plain text, not a PDF';
		$tmpFile = $this->createTempFile('pipeline_bad_mime_', $content);

		$uploadedFile = new UploadedFile(
			'fake.pdf',
			'application/pdf',
			strlen($content),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/formalities'))
			->withParsedBody(['type' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(415);

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function optionalFileCanBeMissing(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = "%PDF-1.4\n";
		$tmpFile = $this->createTempFile('pipeline_opt_', $content);

		$uploadedFile = new UploadedFile(
			'invoice.pdf',
			'application/pdf',
			strlen($content),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		// Only send required file, not the optional thumbnail
		$request = (new ApiRequest(method: 'POST', uri: '/formalities'))
			->withParsedBody(['type' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());

			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('invoice', $payload['type']);
			self::assertSame('invoice.pdf', $payload['fileName']);
			self::assertNull($payload['thumbnailName']);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function multipleFilesAreHydratedIntoArray(): void
	{
		$dispatcher = $this->createDispatcher();
		$content1 = 'first file content';
		$content2 = 'second file content';
		$tmpFile1 = $this->createTempFile('pipeline_multi1_', $content1);
		$tmpFile2 = $this->createTempFile('pipeline_multi2_', $content2);

		$uploadedFiles = [
			new UploadedFile('doc1.txt', 'text/plain', strlen($content1), $tmpFile1, UPLOAD_ERR_OK),
			new UploadedFile('doc2.txt', 'text/plain', strlen($content2), $tmpFile2, UPLOAD_ERR_OK),
		];

		$request = (new ApiRequest(method: 'POST', uri: '/multi-upload'))
			->withParsedBody(['label' => 'batch'])
			->withUploadedFiles(['attachments' => $uploadedFiles]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());

			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('batch', $payload['label']);
			self::assertSame(2, $payload['fileCount']);
		} finally {
			@unlink($tmpFile1);
			@unlink($tmpFile2);
		}
	}

	#[Test]
	public function enumPropertyIsHydratedFromFormData(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'some text content';
		$tmpFile = $this->createTempFile('pipeline_enum_', $content);

		$uploadedFile = new UploadedFile(
			'notes.txt',
			'text/plain',
			strlen($content),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$request = (new ApiRequest(method: 'POST', uri: '/enum-upload'))
			->withParsedBody(['category' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);
		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());

			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('invoice', $payload['category']);
			self::assertSame('notes.txt', $payload['fileName']);
		} finally {
			@unlink($tmpFile);
		}
	}

	private function createDispatcher(): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('multipartDto.controller')
			->setType(MultipartDtoPipelineController::class);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(MultipartDtoPipelineController::class)
			->willReturn(new MultipartDtoPipelineController());

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));

		$handler = new ServiceHandler($container, $serializer);
		$parameterMapping = new RequestParameterMapping();
		$multipartHydrator = new MultipartEntityHydrator();

		return new ApiDispatcher(
			$router,
			$handler,
			$serializer,
			$parameterMapping,
			null,
			null,
			$multipartHydrator,
		);
	}

	private function createTempFile(string $prefix, string $contents): string
	{
		$tmpFile = tempnam(sys_get_temp_dir(), $prefix);
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, $contents);

		return $tmpFile;
	}

}

class MultipartDtoPipelineController implements Controller
{

	#[Post(path: '/formalities')]
	public function addFormality(PipelineFormalityDto $dto): array
	{
		return [
			'type' => $dto->type,
			'fileName' => $dto->file->getName(),
			'thumbnailName' => $dto->thumbnail?->getName(),
		];
	}

	#[Post(path: '/multi-upload')]
	public function uploadMultiple(PipelineMultiFilesDto $dto): array
	{
		return [
			'label' => $dto->label,
			'fileCount' => count($dto->attachments),
		];
	}

	#[Post(path: '/enum-upload')]
	public function uploadWithEnum(PipelineEnumDto $dto): array
	{
		return [
			'category' => $dto->category->value,
			'fileName' => $dto->file->getName(),
		];
	}

}
