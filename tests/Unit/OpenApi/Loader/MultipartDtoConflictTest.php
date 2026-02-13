<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\Loader\RequestBodyBuilder;
use Sabservis\Api\UI\Controller\Controller;

/**
 * @testdox Conflict detection between method-level FileUpload and DTO-level FileUpload
 */
final class MultipartDtoConflictTest extends TestCase
{

	public function testDtoIsDetectedAsHavingFileUploadProperties(): void
	{
		$builder = new RequestBodyBuilder();
		$this->assertTrue($builder->hasFileUploadProperties(DtoWithFileUpload::class));
	}

	public function testThrowsWhenMethodHasFileUploadAndDtoHasFileUpload(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('conflictController')
			->setType(ConflictController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('both method-level #[FileUpload] and a DTO with FileUpload properties');

		$loader->load();
	}

	public function testDtoWithFileUploadGeneratesMultipartFormData(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(DtoOnlyController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];
		$this->assertArrayHasKey('requestBody', $endpoint);

		$requestBody = $endpoint['requestBody'];
		$this->assertArrayHasKey('contentSpec', $requestBody);
		$this->assertArrayHasKey('multipart/form-data', $requestBody['contentSpec']);
	}

}

#[Schema]
final class DtoWithFileUpload
{

	#[FileUpload(name: 'document')]
	public UploadedFile $document;

}

final class DtoOnlyController implements Controller
{

	#[Post(path: '/upload-dto')]
	#[RequestBody(ref: DtoWithFileUpload::class)]
	public function upload(DtoWithFileUpload $input): ApiResponse
	{
		return ApiResponse::json(['status' => 'ok']);
	}

}

final class ConflictController implements Controller
{

	#[Post(path: '/conflict')]
	#[FileUpload(name: 'extra')]
	#[RequestBody(ref: DtoWithFileUpload::class)]
	public function upload(DtoWithFileUpload $input): ApiResponse
	{
		return ApiResponse::json(['status' => 'ok']);
	}

}
