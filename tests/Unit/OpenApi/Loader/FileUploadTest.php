<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\UI\Controller\Controller;

final class FileUploadTest extends TestCase
{

	#[Test]
	public function singleFileUploadGeneratesMultipartRequestBody(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithSingleFileUpload::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		self::assertCount(1, $endpoints);

		$endpoint = $endpoints[0];
		self::assertArrayHasKey('requestBody', $endpoint);

		$requestBody = $endpoint['requestBody'];
		self::assertArrayHasKey('contentSpec', $requestBody);
		self::assertArrayHasKey('multipart/form-data', $requestBody['contentSpec']);

		$schema = $requestBody['contentSpec']['multipart/form-data']['schema'];
		self::assertSame('object', $schema['type']);
		self::assertArrayHasKey('properties', $schema);
		self::assertArrayHasKey('avatar', $schema['properties']);

		$avatarProp = $schema['properties']['avatar'];
		self::assertSame('string', $avatarProp['type']);
		self::assertSame('binary', $avatarProp['format']);
	}

	#[Test]
	public function multipleFileUploadGeneratesArraySchema(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithMultipleFileUpload::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];
		$schema = $endpoint['requestBody']['contentSpec']['multipart/form-data']['schema'];

		self::assertArrayHasKey('documents', $schema['properties']);

		$docsProp = $schema['properties']['documents'];
		self::assertSame('array', $docsProp['type']);
		self::assertArrayHasKey('items', $docsProp);
		self::assertSame('string', $docsProp['items']['type']);
		self::assertSame('binary', $docsProp['items']['format']);
	}

	#[Test]
	public function multipleFileUploadAttributesGenerateMultipleProperties(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithMixedFileUploads::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];
		$schema = $endpoint['requestBody']['contentSpec']['multipart/form-data']['schema'];

		// Should have both properties
		self::assertArrayHasKey('avatar', $schema['properties']);
		self::assertArrayHasKey('documents', $schema['properties']);

		// avatar is single file
		self::assertSame('string', $schema['properties']['avatar']['type']);

		// documents is multiple
		self::assertSame('array', $schema['properties']['documents']['type']);
	}

	#[Test]
	public function requiredFileUploadsAreMarkedInSchema(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithRequiredAndOptionalUploads::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];
		$schema = $endpoint['requestBody']['contentSpec']['multipart/form-data']['schema'];

		// Only 'avatar' is required, 'documents' is optional
		self::assertArrayHasKey('required', $schema);
		self::assertContains('avatar', $schema['required']);
		self::assertNotContains('documents', $schema['required']);
	}

	#[Test]
	public function fileUploadDescriptionIsIncluded(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(TestControllerWithFileUploadDescription::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoint = $result['endpoints'][0];
		$schema = $endpoint['requestBody']['contentSpec']['multipart/form-data']['schema'];

		$avatarProp = $schema['properties']['avatar'];
		self::assertArrayHasKey('description', $avatarProp);
		self::assertSame('User profile picture', $avatarProp['description']);
	}

}

// Test controllers

class TestControllerWithSingleFileUpload implements Controller
{

	#[Post(path: '/upload')]
	#[FileUpload(name: 'avatar')]
	public function upload(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class TestControllerWithMultipleFileUpload implements Controller
{

	#[Post(path: '/upload')]
	#[FileUpload(name: 'documents', multiple: true)]
	public function upload(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class TestControllerWithMixedFileUploads implements Controller
{

	#[Post(path: '/profile')]
	#[FileUpload(name: 'avatar')]
	#[FileUpload(name: 'documents', multiple: true)]
	public function upload(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class TestControllerWithRequiredAndOptionalUploads implements Controller
{

	#[Post(path: '/upload')]
	#[FileUpload(name: 'avatar', required: true)]
	#[FileUpload(name: 'documents', required: false)]
	public function upload(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class TestControllerWithFileUploadDescription implements Controller
{

	#[Post(path: '/upload')]
	#[FileUpload(name: 'avatar', description: 'User profile picture')]
	public function upload(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}
