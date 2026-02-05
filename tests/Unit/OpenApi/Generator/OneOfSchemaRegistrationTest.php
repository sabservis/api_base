<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Property;
use function json_decode;
use function json_encode;

final class OneOfSchemaRegistrationTest extends TestCase
{

	#[Test]
	public function itemsGetReferencedClassesExtractsFromOneOf(): void
	{
		$items = new Items(
			oneOf: [
				OneOfClassA::class,
				OneOfClassB::class,
			],
		);

		$classes = $items->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
		self::assertCount(2, $classes);
	}

	#[Test]
	public function itemsGetReferencedClassesExtractsFromAnyOf(): void
	{
		$items = new Items(
			anyOf: [
				OneOfClassA::class,
				OneOfClassB::class,
			],
		);

		$classes = $items->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
	}

	#[Test]
	public function itemsGetReferencedClassesExtractsFromAllOf(): void
	{
		$items = new Items(
			allOf: [
				OneOfClassA::class,
				OneOfClassB::class,
			],
		);

		$classes = $items->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
	}

	#[Test]
	public function itemsGetReferencedClassesExtractsFromRef(): void
	{
		$items = new Items(
			ref: OneOfClassA::class,
		);

		$classes = $items->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertCount(1, $classes);
	}

	#[Test]
	public function itemsToOpenApiSpecUsesShortClassNameInRef(): void
	{
		$items = new Items(
			ref: OneOfClassA::class,
		);

		$spec = $items->toOpenApiSpec();

		self::assertSame('#/components/schemas/OneOfClassA', $spec['$ref']);
	}

	#[Test]
	public function itemsToOpenApiSpecUsesShortClassNameInOneOf(): void
	{
		$items = new Items(
			oneOf: [
				OneOfClassA::class,
				OneOfClassB::class,
			],
		);

		$spec = $items->toOpenApiSpec();

		self::assertArrayHasKey('oneOf', $spec);
		self::assertCount(2, $spec['oneOf']);
		self::assertSame('#/components/schemas/OneOfClassA', $spec['oneOf'][0]['$ref']);
		self::assertSame('#/components/schemas/OneOfClassB', $spec['oneOf'][1]['$ref']);
	}

	#[Test]
	public function propertyGetReferencedClassesExtractsFromRef(): void
	{
		$property = new Property(
			property: 'test',
			ref: OneOfClassA::class,
		);

		$classes = $property->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
	}

	#[Test]
	public function propertyGetReferencedClassesExtractsFromItems(): void
	{
		$property = new Property(
			property: 'test',
			type: 'array',
			items: new Items(
				oneOf: [
					OneOfClassA::class,
					OneOfClassB::class,
				],
			),
		);

		$classes = $property->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
	}

	#[Test]
	public function propertyToOpenApiSpecUsesShortClassNameInRef(): void
	{
		$property = new Property(
			property: 'test',
			ref: OneOfClassA::class,
		);

		$spec = $property->toOpenApiSpec();

		self::assertSame('#/components/schemas/OneOfClassA', $spec['$ref']);
	}

	#[Test]
	public function jsonContentGetReferencedClassesExtractsFromRef(): void
	{
		$content = new JsonContent(
			ref: OneOfClassA::class,
		);

		$classes = $content->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
	}

	#[Test]
	public function jsonContentGetReferencedClassesExtractsFromOneOf(): void
	{
		$content = new JsonContent(
			oneOf: [
				OneOfClassA::class,
				OneOfClassB::class,
			],
		);

		$classes = $content->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
	}

	#[Test]
	public function jsonContentGetReferencedClassesExtractsFromItems(): void
	{
		$content = new JsonContent(
			type: 'array',
			items: new Items(
				oneOf: [
					OneOfClassA::class,
					OneOfClassB::class,
				],
			),
		);

		$classes = $content->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
	}

	#[Test]
	public function jsonContentGetReferencedClassesExtractsFromProperties(): void
	{
		$content = new JsonContent(
			type: 'object',
			properties: [
				new Property(
					property: 'item',
					ref: OneOfClassA::class,
				),
				new Property(
					property: 'items',
					type: 'array',
					items: new Items(
						ref: OneOfClassB::class,
					),
				),
			],
		);

		$classes = $content->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertContains(OneOfClassB::class, $classes);
	}

	#[Test]
	public function jsonContentToOpenApiSpecUsesShortClassNameInRef(): void
	{
		$content = new JsonContent(
			ref: OneOfClassA::class,
		);

		$spec = $content->toOpenApiSpec();

		self::assertSame('#/components/schemas/OneOfClassA', $spec['schema']['$ref']);
	}

	#[Test]
	public function shortClassNameWorksWithSimpleNames(): void
	{
		// Test with simple name (no namespace) - should be used as-is
		$items = new Items(
			ref: 'SimpleRef',
		);

		$spec = $items->toOpenApiSpec();

		self::assertSame('#/components/schemas/SimpleRef', $spec['$ref']);
	}

	#[Test]
	public function getReferencedClassesIgnoresNonClassStrings(): void
	{
		// Simple string without namespace should not be treated as class
		$items = new Items(
			oneOf: [
				OneOfClassA::class,
				'string', // Not a class name (no backslash)
			],
		);

		$classes = $items->getReferencedClasses();

		self::assertContains(OneOfClassA::class, $classes);
		self::assertNotContains('string', $classes);
		self::assertCount(1, $classes);
	}

}

// Test classes for oneOf registration

class OneOfClassA
{

	public string $typeA;

}

class OneOfClassB
{

	public string $typeB;

}
