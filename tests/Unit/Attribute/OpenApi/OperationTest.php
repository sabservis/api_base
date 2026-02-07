<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;

final class OperationTest extends TestCase
{

	#[Test]
	public function minimalGet(): void
	{
		$get = new Get('/users');

		self::assertSame('GET', $get->getMethod());
		self::assertSame('/users', $get->getPath());
		self::assertSame([], $get->toOpenApiSpec());
	}

	#[Test]
	public function withSummary(): void
	{
		$get = new Get('/users', summary: 'List all users');

		$spec = $get->toOpenApiSpec();

		self::assertSame('List all users', $spec['summary']);
	}

	#[Test]
	public function withDescription(): void
	{
		$get = new Get('/users', description: 'Returns a paginated list of users with optional filtering.');

		$spec = $get->toOpenApiSpec();

		self::assertSame('Returns a paginated list of users with optional filtering.', $spec['description']);
	}

	#[Test]
	public function withSummaryAndDescription(): void
	{
		$get = new Get(
			'/users/{id}',
			summary: 'Get user',
			description: 'Returns detailed user information including profile data and preferences.',
		);

		$spec = $get->toOpenApiSpec();

		self::assertSame('Get user', $spec['summary']);
		self::assertSame(
			'Returns detailed user information including profile data and preferences.',
			$spec['description'],
		);
	}

	#[Test]
	public function withOperationId(): void
	{
		$get = new Get('/users', operationId: 'listUsers');

		$spec = $get->toOpenApiSpec();

		self::assertSame('listUsers', $spec['operationId']);
	}

	#[Test]
	public function withDeprecated(): void
	{
		$get = new Get('/users/legacy', deprecated: true);

		$spec = $get->toOpenApiSpec();

		self::assertTrue($spec['deprecated']);
	}

	#[Test]
	public function deprecatedFalseNotIncluded(): void
	{
		$get = new Get('/users', deprecated: false);

		$spec = $get->toOpenApiSpec();

		self::assertArrayNotHasKey('deprecated', $spec);
	}

	#[Test]
	public function fullSpec(): void
	{
		$post = new Post(
			'/users',
			summary: 'Create user',
			description: 'Creates a new user account with the provided data.',
			operationId: 'createUser',
			deprecated: true,
		);

		$spec = $post->toOpenApiSpec();

		self::assertSame('POST', $post->getMethod());
		self::assertSame('/users', $post->getPath());
		self::assertSame('createUser', $spec['operationId']);
		self::assertSame('Create user', $spec['summary']);
		self::assertSame('Creates a new user account with the provided data.', $spec['description']);
		self::assertTrue($spec['deprecated']);
	}

	#[Test]
	public function descriptionSupportsMarkdown(): void
	{
		$description = <<<'MD'
Returns user details.

## Response fields
- `id`: User ID
- `name`: Full name
- `email`: Email address

**Note:** Some fields may be hidden based on permissions.
MD;

		$get = new Get('/users/{id}', description: $description);

		$spec = $get->toOpenApiSpec();

		self::assertSame($description, $spec['description']);
		self::assertStringContainsString('## Response fields', $spec['description']);
	}

}
