<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\OpenApi\Loader\RequestBodyBuilder;

final class RequestBodyBuilderTest extends TestCase
{

	private RequestBodyBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new RequestBodyBuilder();
	}

	#[Test]
	public function parseRequestBodyAttributeParsesAttribute(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'createUser');
		$requestBody = null;

		$this->builder->parseRequestBodyAttribute($method, $requestBody);

		self::assertNotNull($requestBody);
		self::assertSame('Create user request', $requestBody['description']);
		self::assertTrue($requestBody['required']);
	}

	#[Test]
	public function parseRequestBodyAttributeSkipsWhenAlreadySet(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'createUser');
		$requestBody = ['description' => 'Already set'];

		$this->builder->parseRequestBodyAttribute($method, $requestBody);

		self::assertSame('Already set', $requestBody['description']);
	}

	#[Test]
	public function parseRequestBodyAttributeSkipsMethodWithoutAttribute(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'listUsers');
		$requestBody = null;

		$this->builder->parseRequestBodyAttribute($method, $requestBody);

		self::assertNull($requestBody);
	}

	#[Test]
	public function parseFileUploadAttributesParsesFileUpload(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'uploadFile');
		$requestBody = null;

		$this->builder->parseFileUploadAttributes($method, $requestBody);

		self::assertNotNull($requestBody);
		self::assertSame('File upload', $requestBody['description']);
		self::assertArrayHasKey('multipart/form-data', $requestBody['contentSpec']);
		self::assertArrayHasKey('file', $requestBody['contentSpec']['multipart/form-data']['schema']['properties']);
	}

	#[Test]
	public function parseFileUploadAttributesHandlesMultipleFiles(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'uploadMultiple');
		$requestBody = null;

		$this->builder->parseFileUploadAttributes($method, $requestBody);

		self::assertNotNull($requestBody);
		$properties = $requestBody['contentSpec']['multipart/form-data']['schema']['properties'];
		self::assertArrayHasKey('files', $properties);
		self::assertSame('array', $properties['files']['type']);
	}

	#[Test]
	public function parseFileUploadAttributesSetsRequired(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'uploadRequired');
		$requestBody = null;

		$this->builder->parseFileUploadAttributes($method, $requestBody);

		self::assertNotNull($requestBody);
		self::assertTrue($requestBody['required']);
		self::assertContains('document', $requestBody['contentSpec']['multipart/form-data']['schema']['required']);
	}

	#[Test]
	public function parseFileUploadAttributesSkipsMethodWithoutAttribute(): void
	{
		$method = new ReflectionMethod(RequestBodyTestController::class, 'listUsers');
		$requestBody = null;

		$this->builder->parseFileUploadAttributes($method, $requestBody);

		self::assertNull($requestBody);
	}

	#[Test]
	public function buildRequestBodyBuildsFromAttribute(): void
	{
		$requestBody = new RequestBody(
			description: 'Test request body',
			required: true,
			ref: CreateUserDto::class,
		);

		$result = $this->builder->buildRequestBody($requestBody);

		self::assertSame('Test request body', $result['description']);
		self::assertTrue($result['required']);
		self::assertSame(CreateUserDto::class, $result['entity']);
	}

	#[Test]
	public function buildRequestBodyExtractsRefFromJsonContent(): void
	{
		$requestBody = new RequestBody(
			description: 'JSON content request',
			content: [
				new JsonContent(ref: CreateUserDto::class),
			],
		);

		$result = $this->builder->buildRequestBody($requestBody);

		self::assertSame(CreateUserDto::class, $result['entity']);
	}

	#[Test]
	public function buildRequestBodyBuildsContentSpecForInlineSchema(): void
	{
		$requestBody = new RequestBody(
			description: 'Inline schema request',
			content: [
				new JsonContent(type: 'object', description: 'Custom schema'),
			],
		);

		$result = $this->builder->buildRequestBody($requestBody);

		self::assertNull($result['entity']);
		self::assertNotNull($result['contentSpec']);
		self::assertArrayHasKey('application/json', $result['contentSpec']);
	}

}

// Test fixtures

class RequestBodyTestController
{

	#[RequestBody(description: 'Create user request', required: true, ref: CreateUserDto::class)]
	public function createUser(): void
	{
		// Test fixture - attributes are what matters
	}

	public function listUsers(): void
	{
		// Test fixture - no attributes
	}

	#[FileUpload(name: 'file', description: 'File to upload')]
	public function uploadFile(): void
	{
		// Test fixture - attributes are what matters
	}

	#[FileUpload(name: 'files', description: 'Multiple files', multiple: true)]
	public function uploadMultiple(): void
	{
		// Test fixture - attributes are what matters
	}

	#[FileUpload(name: 'document', description: 'Required document', required: true)]
	public function uploadRequired(): void
	{
		// Test fixture - attributes are what matters
	}

}

class CreateUserDto
{

	public string $name;

	public string $email;

}
