<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Loader\RequestBodyBuilder;

final class MultipartDtoDetectionTest extends TestCase
{

	private RequestBodyBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new RequestBodyBuilder();
	}

	#[Test]
	public function detectsFileUploadPropertiesInDto(): void
	{
		self::assertTrue($this->builder->hasFileUploadProperties(MultipartDto::class));
	}

	#[Test]
	public function returnsFalseForRegularDto(): void
	{
		self::assertFalse($this->builder->hasFileUploadProperties(RegularDto::class));
	}

	#[Test]
	public function buildsMultipartSpecFromDto(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartDto::class);

		self::assertSame(MultipartDto::class, $result['entity']);
		self::assertArrayHasKey('multipart/form-data', $result['contentSpec']);

		$schema = $result['contentSpec']['multipart/form-data']['schema'];
		self::assertSame('object', $schema['type']);

		// Regular property mapped to string
		self::assertArrayHasKey('title', $schema['properties']);
		self::assertSame('string', $schema['properties']['title']['type']);

		// File upload property mapped to binary
		self::assertArrayHasKey('attachment', $schema['properties']);
		self::assertSame('string', $schema['properties']['attachment']['type']);
		self::assertSame('binary', $schema['properties']['attachment']['format']);

		// Both non-nullable properties are required
		self::assertContains('title', $schema['required']);
		self::assertContains('attachment', $schema['required']);
	}

	#[Test]
	public function optionalFileUploadPropertyIsNotRequired(): void
	{
		$result = $this->builder->buildMultipartRequestBody(OptionalFileUploadDto::class);

		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		// Nullable file upload should not be required
		if (isset($schema['required'])) {
			self::assertNotContains('file', $schema['required']);
		} else {
			// No required array at all is also valid
			self::assertTrue(true);
		}
	}

	#[Test]
	public function multipleFileUploadGeneratesArraySchema(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipleFilesDto::class);

		$schema = $result['contentSpec']['multipart/form-data']['schema'];
		$filesProp = $schema['properties']['files'];

		self::assertSame('array', $filesProp['type']);
		self::assertSame('string', $filesProp['items']['type']);
		self::assertSame('binary', $filesProp['items']['format']);
	}

	#[Test]
	public function fileUploadInstancesAreExtractedFromProperties(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartDto::class);

		self::assertArrayHasKey('fileUploads', $result);
		self::assertNotEmpty($result['fileUploads']);
		self::assertContainsOnlyInstancesOf(FileUpload::class, $result['fileUploads']);
	}

	#[Test]
	public function explicitFileUploadNameOverridesPropertyName(): void
	{
		$result = $this->builder->buildMultipartRequestBody(ExplicitNameDto::class);

		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		// The explicit name from attribute should be used instead of property name
		self::assertArrayHasKey('custom_file_name', $schema['properties']);
		self::assertArrayNotHasKey('file', $schema['properties']);
	}

	#[Test]
	public function descriptionFromFileUploadAttribute(): void
	{
		$result = $this->builder->buildMultipartRequestBody(DescribedFileUploadDto::class);

		$schema = $result['contentSpec']['multipart/form-data']['schema'];
		$prop = $schema['properties']['document'];

		self::assertArrayHasKey('description', $prop);
		self::assertSame('Upload your PDF document', $prop['description']);
	}

	#[Test]
	public function regularPropertyWithPropertyAttributePreservesDescription(): void
	{
		$result = $this->builder->buildMultipartRequestBody(DescribedPropertyDto::class);

		$schema = $result['contentSpec']['multipart/form-data']['schema'];
		$prop = $schema['properties']['name'];

		self::assertArrayHasKey('description', $prop);
		self::assertSame('The user name', $prop['description']);
	}

}

// Test fixture DTOs

class MultipartDto
{

	public string $title;

	#[FileUpload]
	public UploadedFile $attachment;

}

class RegularDto
{

	public string $name;

	public int $age;

}

class OptionalFileUploadDto
{

	#[FileUpload]
	public UploadedFile|null $file = null;

}

class MultipleFilesDto
{

	#[FileUpload(multiple: true)]
	public UploadedFile $files;

}

class ExplicitNameDto
{

	#[FileUpload(name: 'custom_file_name')]
	public UploadedFile $file;

}

class DescribedFileUploadDto
{

	#[FileUpload(description: 'Upload your PDF document')]
	public UploadedFile $document;

}

class DescribedPropertyDto
{

	#[Property(description: 'The user name')]
	public string $name;

	#[FileUpload]
	public UploadedFile $avatar;

}
