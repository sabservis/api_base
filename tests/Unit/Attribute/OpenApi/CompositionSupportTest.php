<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Schema;

/**
 * Tests for CompositionSupport trait used by Items, JsonContent, and Schema.
 */
final class CompositionSupportTest extends TestCase
{

	#[Test]
	public function itemsOneOfWithClassReferences(): void
	{
		// Use existing classes from the codebase
		$items = new Items(oneOf: [
			Schema::class,
			Items::class,
		]);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('oneOf', $spec);
		self::assertCount(2, $spec['oneOf']);
		self::assertSame(['$ref' => '#/components/schemas/Schema'], $spec['oneOf'][0]);
		self::assertSame(['$ref' => '#/components/schemas/Items'], $spec['oneOf'][1]);
	}

	#[Test]
	public function itemsAnyOfWithClassReferences(): void
	{
		$items = new Items(anyOf: [
			Schema::class,
			JsonContent::class,
		]);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('anyOf', $spec);
		self::assertCount(2, $spec['anyOf']);
		self::assertSame(['$ref' => '#/components/schemas/Schema'], $spec['anyOf'][0]);
		self::assertSame(['$ref' => '#/components/schemas/JsonContent'], $spec['anyOf'][1]);
	}

	#[Test]
	public function itemsAllOfWithClassReferences(): void
	{
		$items = new Items(allOf: [
			Schema::class,
			Items::class,
		]);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('allOf', $spec);
		self::assertCount(2, $spec['allOf']);
		self::assertSame(['$ref' => '#/components/schemas/Schema'], $spec['allOf'][0]);
		self::assertSame(['$ref' => '#/components/schemas/Items'], $spec['allOf'][1]);
	}

	#[Test]
	public function itemsOneOfWithSchemaObjects(): void
	{
		$items = new Items(oneOf: [
			new Schema(type: 'string'),
			new Schema(type: 'integer'),
		]);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('oneOf', $spec);
		self::assertCount(2, $spec['oneOf']);
		self::assertSame(['type' => 'string'], $spec['oneOf'][0]);
		self::assertSame(['type' => 'integer'], $spec['oneOf'][1]);
	}

	#[Test]
	public function itemsOneOfWithRawArray(): void
	{
		$items = new Items(oneOf: [
			['type' => 'string', 'format' => 'email'],
			['type' => 'null'],
		]);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('oneOf', $spec);
		self::assertCount(2, $spec['oneOf']);
		self::assertSame(['type' => 'string', 'format' => 'email'], $spec['oneOf'][0]);
		self::assertSame(['type' => 'null'], $spec['oneOf'][1]);
	}

	#[Test]
	public function jsonContentOneOfWithClassReferences(): void
	{
		$jsonContent = new JsonContent(
			type: 'array',
			items: new Items(oneOf: [
				Schema::class,
				Items::class,
			]),
		);

		$spec = $jsonContent->toOpenApiSpec();

		self::assertArrayHasKey('schema', $spec);
		self::assertArrayHasKey('items', $spec['schema']);
		self::assertArrayHasKey('oneOf', $spec['schema']['items']);
	}

	#[Test]
	public function schemaOneOfWithClassReferences(): void
	{
		$schema = new Schema(oneOf: [
			Schema::class,
			Items::class,
		]);

		$spec = $schema->toOpenApiSpec();

		self::assertArrayHasKey('oneOf', $spec);
		self::assertCount(2, $spec['oneOf']);
		self::assertSame(['$ref' => '#/components/schemas/Schema'], $spec['oneOf'][0]);
		self::assertSame(['$ref' => '#/components/schemas/Items'], $spec['oneOf'][1]);
	}

	#[Test]
	public function itemsGetReferencedClassesFromOneOf(): void
	{
		// getReferencedClasses uses looksLikeClassName (contains backslash)
		// so non-existent classes are still detected
		$items = new Items(oneOf: [
			'App\\DTO\\ArticleDto',
			'App\\DTO\\VideoDto',
		]);

		$classes = $items->getReferencedClasses();

		self::assertCount(2, $classes);
		self::assertContains('App\\DTO\\ArticleDto', $classes);
		self::assertContains('App\\DTO\\VideoDto', $classes);
	}

	#[Test]
	public function itemsGetReferencedClassesFromRef(): void
	{
		$items = new Items(ref: 'App\\DTO\\UserDto');

		$classes = $items->getReferencedClasses();

		self::assertCount(1, $classes);
		self::assertContains('App\\DTO\\UserDto', $classes);
	}

	#[Test]
	public function itemsGetReferencedClassesFromAllCompositions(): void
	{
		$items = new Items(
			ref: 'App\\DTO\\MainDto',
			oneOf: ['App\\DTO\\OneDto'],
			anyOf: ['App\\DTO\\AnyDto'],
			allOf: ['App\\DTO\\AllDto'],
		);

		$classes = $items->getReferencedClasses();

		self::assertCount(4, $classes);
		self::assertContains('App\\DTO\\MainDto', $classes);
		self::assertContains('App\\DTO\\OneDto', $classes);
		self::assertContains('App\\DTO\\AnyDto', $classes);
		self::assertContains('App\\DTO\\AllDto', $classes);
	}

	#[Test]
	public function jsonContentGetReferencedClassesFromItems(): void
	{
		$jsonContent = new JsonContent(
			type: 'array',
			items: new Items(oneOf: [
				'App\\DTO\\ArticleDto',
				'App\\DTO\\VideoDto',
			]),
		);

		$classes = $jsonContent->getReferencedClasses();

		self::assertCount(2, $classes);
		self::assertContains('App\\DTO\\ArticleDto', $classes);
		self::assertContains('App\\DTO\\VideoDto', $classes);
	}

	#[Test]
	public function schemaRefExtractsShortName(): void
	{
		// Note: Schema::toOpenApiSpec() uses looksLikeClassName for ref extraction
		$schema = new Schema(ref: 'App\\DTO\\UserDto');

		$spec = $schema->toOpenApiSpec();

		self::assertSame(['$ref' => '#/components/schemas/UserDto'], $spec);
	}

	#[Test]
	public function itemsRefExtractsShortName(): void
	{
		// Note: Items::toOpenApiSpec() uses looksLikeClassName for ref extraction
		$items = new Items(ref: 'App\\DTO\\UserDto');

		$spec = $items->toOpenApiSpec();

		self::assertSame(['$ref' => '#/components/schemas/UserDto'], $spec);
	}

	#[Test]
	public function looksLikeClassNameRequiresBackslash(): void
	{
		$items = new Items(ref: 'SimpleString');

		$classes = $items->getReferencedClasses();

		// SimpleString doesn't contain backslash, so it's not treated as a class
		self::assertCount(0, $classes);
	}

}
