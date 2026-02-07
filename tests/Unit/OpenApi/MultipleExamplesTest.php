<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Examples;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\MediaType;

final class MultipleExamplesTest extends TestCase
{

	#[Test]
	public function examplesAttributeToOpenApiSpec(): void
	{
		$example = new Examples(
			example: 'success',
			summary: 'Successful response',
			description: 'Returns user data',
			value: ['id' => 1, 'name' => 'John'],
		);

		$spec = $example->toOpenApiSpec();

		self::assertSame('Successful response', $spec['summary']);
		self::assertSame('Returns user data', $spec['description']);
		self::assertSame(['id' => 1, 'name' => 'John'], $spec['value']);
		self::assertSame('success', $example->getName());
	}

	#[Test]
	public function mediaTypeWithMultipleExamples(): void
	{
		$mediaType = new MediaType(
			mediaType: 'application/json',
			examples: [
				new Examples(example: 'success', summary: 'Success', value: ['id' => 1]),
				new Examples(example: 'empty', summary: 'Empty', value: []),
			],
		);

		$spec = $mediaType->toOpenApiSpec();

		self::assertArrayHasKey('application/json', $spec);
		$jsonSpec = $spec['application/json'];

		self::assertArrayHasKey('examples', $jsonSpec);
		self::assertArrayHasKey('success', $jsonSpec['examples']);
		self::assertArrayHasKey('empty', $jsonSpec['examples']);

		self::assertSame('Success', $jsonSpec['examples']['success']['summary']);
		self::assertSame(['id' => 1], $jsonSpec['examples']['success']['value']);

		self::assertSame('Empty', $jsonSpec['examples']['empty']['summary']);
		self::assertSame([], $jsonSpec['examples']['empty']['value']);
	}

	#[Test]
	public function mediaTypeWithSingleExampleDoesNotHaveExamplesKey(): void
	{
		$mediaType = new MediaType(
			mediaType: 'application/json',
			example: ['id' => 1],
		);

		$spec = $mediaType->toOpenApiSpec();

		self::assertArrayHasKey('application/json', $spec);
		self::assertArrayHasKey('example', $spec['application/json']);
		self::assertArrayNotHasKey('examples', $spec['application/json']);
	}

	#[Test]
	public function jsonContentWithMultipleExamples(): void
	{
		$jsonContent = new JsonContent(
			type: 'object',
			examples: [
				new Examples(example: 'user', summary: 'User object', value: ['id' => 1, 'name' => 'John']),
				new Examples(
					example: 'admin',
					summary: 'Admin object',
					value: ['id' => 2, 'name' => 'Admin', 'role' => 'admin'],
				),
			],
		);

		$spec = $jsonContent->toOpenApiSpec();

		self::assertArrayHasKey('schema', $spec);
		self::assertArrayHasKey('examples', $spec);
		self::assertArrayHasKey('user', $spec['examples']);
		self::assertArrayHasKey('admin', $spec['examples']);

		self::assertSame('User object', $spec['examples']['user']['summary']);
		self::assertSame('Admin object', $spec['examples']['admin']['summary']);
	}

	#[Test]
	public function jsonContentWithSingleExampleInSchema(): void
	{
		$jsonContent = new JsonContent(
			type: 'object',
			example: ['id' => 1],
		);

		$spec = $jsonContent->toOpenApiSpec();

		self::assertArrayHasKey('schema', $spec);
		self::assertArrayHasKey('example', $spec['schema']);
		self::assertArrayNotHasKey('examples', $spec);
	}

	#[Test]
	public function examplesWithExternalValue(): void
	{
		$example = new Examples(
			example: 'external',
			summary: 'External example',
			externalValue: 'https://example.com/example.json',
		);

		$spec = $example->toOpenApiSpec();

		self::assertSame('External example', $spec['summary']);
		self::assertSame('https://example.com/example.json', $spec['externalValue']);
		self::assertArrayNotHasKey('value', $spec);
	}

	#[Test]
	public function examplesWithDefaultName(): void
	{
		$mediaType = new MediaType(
			mediaType: 'application/json',
			examples: [
				new Examples(summary: 'Default example', value: ['test' => true]),
			],
		);

		$spec = $mediaType->toOpenApiSpec();

		// When example name is null, it should use 'default' as key
		self::assertArrayHasKey('default', $spec['application/json']['examples']);
	}

}
