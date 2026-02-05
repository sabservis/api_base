<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Response;

final class ResponseTest extends TestCase
{

	#[Test]
	public function singleDtoRef(): void
	{
		$response = new Response(response: 200, ref: TestUserDto::class);

		$spec = $response->toOpenApiSpec();

		self::assertSame('OK', $spec['description']);
		self::assertArrayHasKey('content', $spec);
		self::assertArrayHasKey('application/json', $spec['content']);
		self::assertSame(
			['$ref' => '#/components/schemas/TestUserDto'],
			$spec['content']['application/json']['schema'],
		);
	}

	#[Test]
	public function listRefWithoutMeta(): void
	{
		$response = new Response(response: 200, listRef: TestUserDto::class);

		$spec = $response->toOpenApiSpec();

		self::assertSame('OK', $spec['description']);
		self::assertArrayHasKey('content', $spec);

		$schema = $spec['content']['application/json']['schema'];
		self::assertSame('array', $schema['type']);
		self::assertSame(['$ref' => '#/components/schemas/TestUserDto'], $schema['items']);
	}

	#[Test]
	public function listRefWithMeta(): void
	{
		$response = new Response(response: 200, listRef: TestUserDto::class, withMeta: true);

		$spec = $response->toOpenApiSpec();

		self::assertSame('OK', $spec['description']);
		self::assertArrayHasKey('content', $spec);

		$schema = $spec['content']['application/json']['schema'];
		self::assertSame('object', $schema['type']);
		self::assertArrayHasKey('properties', $schema);

		// Check data property
		self::assertArrayHasKey('data', $schema['properties']);
		self::assertSame('array', $schema['properties']['data']['type']);
		self::assertSame(
			['$ref' => '#/components/schemas/TestUserDto'],
			$schema['properties']['data']['items'],
		);

		// Check meta property
		self::assertArrayHasKey('meta', $schema['properties']);
		self::assertSame('object', $schema['properties']['meta']['type']);
		self::assertArrayHasKey('total', $schema['properties']['meta']['properties']);
		self::assertArrayHasKey('limit', $schema['properties']['meta']['properties']);
		self::assertArrayHasKey('offset', $schema['properties']['meta']['properties']);

		// Check required
		self::assertSame(['data', 'meta'], $schema['required']);
	}

	#[Test]
	public function customDescription(): void
	{
		$response = new Response(
			response: 200,
			description: 'List of users',
			listRef: TestUserDto::class,
			withMeta: true,
		);

		$spec = $response->toOpenApiSpec();

		self::assertSame('List of users', $spec['description']);
	}

	#[Test]
	public function defaultDescriptions(): void
	{
		self::assertSame('OK', (new Response(response: 200))->toOpenApiSpec()['description']);
		self::assertSame('Created', (new Response(response: 201))->toOpenApiSpec()['description']);
		self::assertSame('No Content', (new Response(response: 204))->toOpenApiSpec()['description']);
		self::assertSame('Bad Request', (new Response(response: 400))->toOpenApiSpec()['description']);
		self::assertSame('Unauthorized', (new Response(response: 401))->toOpenApiSpec()['description']);
		self::assertSame('Forbidden', (new Response(response: 403))->toOpenApiSpec()['description']);
		self::assertSame('Not Found', (new Response(response: 404))->toOpenApiSpec()['description']);
		self::assertSame('Unprocessable Entity', (new Response(response: 422))->toOpenApiSpec()['description']);
		self::assertSame('Internal Server Error', (new Response(response: 500))->toOpenApiSpec()['description']);
	}

	#[Test]
	public function responseWithStringRef(): void
	{
		$response = new Response(response: 200, ref: 'CustomSchema');

		$spec = $response->toOpenApiSpec();

		self::assertSame(
			['$ref' => '#/components/schemas/CustomSchema'],
			$spec['content']['application/json']['schema'],
		);
	}

	#[Test]
	public function listRefWithStringRef(): void
	{
		$response = new Response(response: 200, listRef: 'CustomSchema');

		$spec = $response->toOpenApiSpec();

		$schema = $spec['content']['application/json']['schema'];
		self::assertSame('array', $schema['type']);
		self::assertSame(['$ref' => '#/components/schemas/CustomSchema'], $schema['items']);
	}

	#[Test]
	public function shortSyntaxWithDefaultStatus(): void
	{
		// #[Response(ref: UserDto::class)] - default 200
		$response = new Response(ref: TestUserDto::class);

		self::assertSame('200', $response->getStatusCode());
		self::assertSame('OK', $response->toOpenApiSpec()['description']);
	}

	#[Test]
	public function shortSyntaxWithPositionalStatus(): void
	{
		// #[Response(201, ref: UserDto::class)]
		$response = new Response(201, ref: TestUserDto::class);

		self::assertSame('201', $response->getStatusCode());
		self::assertSame('Created', $response->toOpenApiSpec()['description']);
	}

	#[Test]
	public function shortSyntaxStatusOnly(): void
	{
		// #[Response(404)]
		$response = new Response(404);

		self::assertSame('404', $response->getStatusCode());
		self::assertSame('Not Found', $response->toOpenApiSpec()['description']);
	}

	#[Test]
	public function shortSyntaxListWithPositionalStatus(): void
	{
		// #[Response(200, listRef: UserDto::class, withMeta: true)]
		$response = new Response(200, listRef: TestUserDto::class, withMeta: true);

		self::assertSame('200', $response->getStatusCode());
		$schema = $response->toOpenApiSpec()['content']['application/json']['schema'];
		self::assertSame('object', $schema['type']);
		self::assertArrayHasKey('data', $schema['properties']);
	}

}

class TestUserDto
{

	public int $id;

	public string $name;

}
