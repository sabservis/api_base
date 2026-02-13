<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;

/**
 * Tests for multipart/form-data generation when DTOs have FileUpload properties.
 */
final class MultipartDtoOpenApiTest extends TestCase
{

	#[Test]
	public function dtoWithFileUploadGeneratesMultipartFormData(): void
	{
		$spec = $this->generateSpecForController(MultipartDtoController::class);

		$requestBody = $spec['paths']['/upload-dto']['post']['requestBody'];

		// Should be multipart/form-data, NOT application/json
		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);
		self::assertArrayNotHasKey('application/json', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];
		self::assertSame('object', $schema['type']);

		// Should have the title property as string
		self::assertArrayHasKey('title', $schema['properties']);
		self::assertSame('string', $schema['properties']['title']['type']);

		// Should have the file property as binary
		self::assertArrayHasKey('file', $schema['properties']);
		self::assertSame('string', $schema['properties']['file']['type']);
		self::assertSame('binary', $schema['properties']['file']['format']);

		// Both title and file should be required (non-nullable)
		self::assertContains('title', $schema['required']);
		self::assertContains('file', $schema['required']);
	}

	#[Test]
	public function regularDtoStillGeneratesApplicationJson(): void
	{
		$spec = $this->generateSpecForController(RegularDtoController::class);

		$requestBody = $spec['paths']['/regular-dto']['post']['requestBody'];

		// Should remain application/json
		self::assertArrayHasKey('application/json', $requestBody['content']);
		self::assertArrayNotHasKey('multipart/form-data', $requestBody['content']);

		// Should reference the DTO schema
		self::assertArrayHasKey('$ref', $requestBody['content']['application/json']['schema']);
		self::assertSame(
			'#/components/schemas/RegularDto',
			$requestBody['content']['application/json']['schema']['$ref'],
		);
	}

	#[Test]
	public function multipartDtoWithEnumGeneratesRefForEnumProperty(): void
	{
		$spec = $this->generateSpecForController(MultipartDtoWithEnumController::class);

		$requestBody = $spec['paths']['/upload-with-enum']['post']['requestBody'];

		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];

		// The category property should be a $ref to the enum
		self::assertArrayHasKey('category', $schema['properties']);
		self::assertArrayHasKey('$ref', $schema['properties']['category']);
		self::assertSame(
			'#/components/schemas/MultipartCategoryEnum',
			$schema['properties']['category']['$ref'],
		);

		// The enum should be registered in components
		self::assertArrayHasKey('components', $spec);
		self::assertArrayHasKey('schemas', $spec['components']);
		self::assertArrayHasKey('MultipartCategoryEnum', $spec['components']['schemas']);
		self::assertSame('string', $spec['components']['schemas']['MultipartCategoryEnum']['type']);
		self::assertContains('photo', $spec['components']['schemas']['MultipartCategoryEnum']['enum']);
		self::assertContains('document', $spec['components']['schemas']['MultipartCategoryEnum']['enum']);
	}

	#[Test]
	public function multipartDtoOptionalFileNotInRequired(): void
	{
		$spec = $this->generateSpecForController(MultipartOptionalFileController::class);

		$requestBody = $spec['paths']['/upload-optional']['post']['requestBody'];

		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];

		// The file property should exist but NOT be required (it's nullable)
		self::assertArrayHasKey('file', $schema['properties']);

		// title should be required, file should NOT be
		self::assertContains('title', $schema['required']);
		self::assertNotContains('file', $schema['required'] ?? []);
	}

	#[Test]
	public function multipartDtoMultipleFilesGeneratesArraySchema(): void
	{
		$spec = $this->generateSpecForController(MultipartMultipleFilesController::class);

		$requestBody = $spec['paths']['/upload-multiple']['post']['requestBody'];

		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];

		// The attachments property should be an array of binary items
		self::assertArrayHasKey('attachments', $schema['properties']);
		self::assertSame('array', $schema['properties']['attachments']['type']);
		self::assertSame('string', $schema['properties']['attachments']['items']['type']);
		self::assertSame('binary', $schema['properties']['attachments']['items']['format']);
	}

	#[Test]
	public function methodLevelFileUploadStillWorks(): void
	{
		$spec = $this->generateSpecForController(MethodLevelFileUploadController::class);

		$requestBody = $spec['paths']['/upload-method-level']['post']['requestBody'];

		// Should be multipart/form-data (from method-level #[FileUpload])
		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];

		// Should have the document property
		self::assertArrayHasKey('document', $schema['properties']);
		self::assertSame('string', $schema['properties']['document']['type']);
		self::assertSame('binary', $schema['properties']['document']['format']);
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<string, mixed>
	 */
	private function generateSpecForController(string $controllerClass): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType($controllerClass);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$spec = $generator->generate($schema);

		return json_decode(json_encode($spec), true);
	}

}

// ==================== TEST DTOS ====================

#[Schema]
class MultipartUploadDto
{

	public string $title;

	#[FileUpload(name: 'file')]
	public UploadedFile $file;

}

#[Schema]
class RegularDto
{

	public string $name;

	public string $email;

}

enum MultipartCategoryEnum: string
{

	case Photo = 'photo';

	case Document = 'document';

}

#[Schema]
class MultipartUploadWithEnumDto
{

	public MultipartCategoryEnum $category;

	#[FileUpload(name: 'file')]
	public UploadedFile $file;

}

#[Schema]
class MultipartOptionalFileDto
{

	public string $title;

	#[FileUpload(name: 'file')]
	public UploadedFile|null $file = null;

}

#[Schema]
class MultipartMultipleFilesDto
{

	public string $title;

	#[FileUpload(name: 'attachments', multiple: true)]
	public UploadedFile $attachments;

}

// ==================== TEST CONTROLLERS ====================

class MultipartDtoController implements Controller
{

	#[Post(path: '/upload-dto')]
	#[RequestBody(ref: MultipartUploadDto::class)]
	public function upload(MultipartUploadDto $input): ApiResponse
	{
		return new ApiResponse();
	}

}

class RegularDtoController implements Controller
{

	#[Post(path: '/regular-dto')]
	#[RequestBody(ref: RegularDto::class)]
	public function create(RegularDto $input): ApiResponse
	{
		return new ApiResponse();
	}

}

class MultipartDtoWithEnumController implements Controller
{

	#[Post(path: '/upload-with-enum')]
	#[RequestBody(ref: MultipartUploadWithEnumDto::class)]
	public function upload(MultipartUploadWithEnumDto $input): ApiResponse
	{
		return new ApiResponse();
	}

}

class MultipartOptionalFileController implements Controller
{

	#[Post(path: '/upload-optional')]
	#[RequestBody(ref: MultipartOptionalFileDto::class)]
	public function upload(MultipartOptionalFileDto $input): ApiResponse
	{
		return new ApiResponse();
	}

}

class MultipartMultipleFilesController implements Controller
{

	#[Post(path: '/upload-multiple')]
	#[RequestBody(ref: MultipartMultipleFilesDto::class)]
	public function upload(MultipartMultipleFilesDto $input): ApiResponse
	{
		return new ApiResponse();
	}

}

class MethodLevelFileUploadController implements Controller
{

	#[Post(path: '/upload-method-level')]
	#[FileUpload(name: 'document', required: true)]
	public function upload(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}
