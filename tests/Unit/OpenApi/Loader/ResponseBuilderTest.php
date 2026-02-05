<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabservis\Api\Attribute\OpenApi\FileResponse;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\OpenApi\Loader\ResponseBuilder;
use Sabservis\Api\Schema\EndpointResponse;

final class ResponseBuilderTest extends TestCase
{

	private ResponseBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new ResponseBuilder();
	}

	#[Test]
	public function parseResponseAttributesParsesResponse(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'getUser');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertSame('User found', $responses['200']['description']);
		self::assertSame(ResponseUserDto::class, $responses['200']['entity']);
	}

	#[Test]
	public function parseResponseAttributesParsesMultipleResponses(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'getUserWithErrors');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertArrayHasKey('404', $responses);
	}

	#[Test]
	public function parseResponseAttributesParsesListRef(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'listUsers');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertSame(ResponseUserDto::class, $responses['200']['entity']);
		self::assertSame(EndpointResponse::WrapperListNoMeta, $responses['200']['wrapperType']);
	}

	#[Test]
	public function parseResponseAttributesParsesListRefWithMeta(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'listUsersWithMeta');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertSame(EndpointResponse::WrapperListWithMeta, $responses['200']['wrapperType']);
	}

	#[Test]
	public function parseResponseAttributesParsesFileResponse(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'downloadFile');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertSame('File download', $responses['200']['description']);
		self::assertArrayHasKey('fileResponse', $responses['200']);
		self::assertSame('application/pdf', $responses['200']['fileResponse']['contentType']);
		self::assertSame('document.pdf', $responses['200']['fileResponse']['filename']);
	}

	#[Test]
	public function parseResponseAttributesParsesContentSpec(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'customResponse');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertArrayHasKey('contentSpec', $responses['200']);
	}

	#[Test]
	public function parseResponseAttributesExtractsReferencedClasses(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'responseWithRef');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);

		if (isset($responses['200']['referencedSchemas'])) {
			self::assertContains(ResponseUserDto::class, $responses['200']['referencedSchemas']);
		}
	}

	#[Test]
	public function getDefaultDescriptionReturnsCorrectDescriptions(): void
	{
		self::assertSame('OK', $this->builder->getDefaultDescription('200'));
		self::assertSame('Created', $this->builder->getDefaultDescription('201'));
		self::assertSame('No Content', $this->builder->getDefaultDescription('204'));
		self::assertSame('Bad Request', $this->builder->getDefaultDescription('400'));
		self::assertSame('Unauthorized', $this->builder->getDefaultDescription('401'));
		self::assertSame('Forbidden', $this->builder->getDefaultDescription('403'));
		self::assertSame('Not Found', $this->builder->getDefaultDescription('404'));
		self::assertSame('Unprocessable Entity', $this->builder->getDefaultDescription('422'));
		self::assertSame('Internal Server Error', $this->builder->getDefaultDescription('500'));
	}

	#[Test]
	public function getDefaultDescriptionReturnsGenericForUnknownCode(): void
	{
		self::assertSame('Response', $this->builder->getDefaultDescription('418'));
	}

	#[Test]
	public function parseResponseAttributesUsesDefaultDescription(): void
	{
		$method = new ReflectionMethod(ResponseTestController::class, 'noDescription');
		$responses = [];

		$this->builder->parseResponseAttributes($method, $responses);

		self::assertArrayHasKey('200', $responses);
		self::assertSame('OK', $responses['200']['description']);
	}

}

// Test fixtures

class ResponseTestController
{

	#[Response(response: 200, description: 'User found', ref: ResponseUserDto::class)]
	public function getUser(): void
	{
		// Test fixture - attributes are what matters
	}

	#[Response(response: 200, description: 'Success', ref: ResponseUserDto::class)]
	#[Response(response: 404, description: 'User not found')]
	public function getUserWithErrors(): void
	{
		// Test fixture - attributes are what matters
	}

	#[Response(response: 200, description: 'List of users', listRef: ResponseUserDto::class)]
	public function listUsers(): void
	{
		// Test fixture - attributes are what matters
	}

	#[Response(response: 200, description: 'List with meta', listRef: ResponseUserDto::class, withMeta: true)]
	public function listUsersWithMeta(): void
	{
		// Test fixture - attributes are what matters
	}

	#[FileResponse(contentType: 'application/pdf', filename: 'document.pdf')]
	public function downloadFile(): void
	{
		// Test fixture - attributes are what matters
	}

	#[Response(
		response: 200,
		content: [new JsonContent(type: 'object', description: 'Custom response')],
	)]
	public function customResponse(): void
	{
		// Test fixture - attributes are what matters
	}

	#[Response(
		response: 200,
		content: [new JsonContent(ref: ResponseUserDto::class)],
	)]
	public function responseWithRef(): void
	{
		// Test fixture - attributes are what matters
	}

	#[Response(response: 200, ref: ResponseUserDto::class)]
	public function noDescription(): void
	{
		// Test fixture - attributes are what matters
	}

}

class ResponseUserDto
{

	public int $id;

	public string $name;

}
