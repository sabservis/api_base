<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Generator\SchemaBuilder;
use Sabservis\Api\OpenApi\Spec\SchemaObject;
use function assert;
use function json_decode;
use function json_encode;

final class NullableRefTest extends TestCase
{

	#[Test]
	public function nullableEnumPropertyGeneratesOneOfWrapper(): void
	{
		$builder = new SchemaBuilder();
		$schemaName = $builder->registerClass(DtoWithNullableEnum::class);

		$schemas = $builder->getSchemas();
		self::assertArrayHasKey($schemaName, $schemas);

		$schema = $schemas[$schemaName];
		$json = json_encode($schema);
		assert($json !== false);
		$data = json_decode($json, true);

		// Check that propertyCategory has oneOf wrapper with nullable
		self::assertArrayHasKey('properties', $data);
		self::assertArrayHasKey('category', $data['properties']);

		$categoryProp = $data['properties']['category'];
		self::assertArrayHasKey('oneOf', $categoryProp);
		self::assertArrayHasKey('nullable', $categoryProp);
		self::assertTrue($categoryProp['nullable']);
		self::assertCount(1, $categoryProp['oneOf']);
		self::assertArrayHasKey('$ref', $categoryProp['oneOf'][0]);
	}

	#[Test]
	public function nonNullableEnumPropertyHasNoOneOf(): void
	{
		$builder = new SchemaBuilder();
		$schemaName = $builder->registerClass(DtoWithRequiredEnum::class);

		$schemas = $builder->getSchemas();
		$schema = $schemas[$schemaName];
		$json = json_encode($schema);
		assert($json !== false);
		$data = json_decode($json, true);

		// Check that category has direct $ref without oneOf
		self::assertArrayHasKey('properties', $data);
		self::assertArrayHasKey('category', $data['properties']);

		$categoryProp = $data['properties']['category'];
		self::assertArrayHasKey('$ref', $categoryProp);
		self::assertArrayNotHasKey('oneOf', $categoryProp);
		self::assertArrayNotHasKey('nullable', $categoryProp);
	}

	#[Test]
	public function nullableClassRefGeneratesOneOfWrapper(): void
	{
		$builder = new SchemaBuilder();
		$schemaName = $builder->registerClass(DtoWithNullableClass::class);

		$schemas = $builder->getSchemas();
		$schema = $schemas[$schemaName];
		$json = json_encode($schema);
		assert($json !== false);
		$data = json_decode($json, true);

		// Check that nested has oneOf wrapper with nullable
		self::assertArrayHasKey('properties', $data);
		self::assertArrayHasKey('nested', $data['properties']);

		$nestedProp = $data['properties']['nested'];
		self::assertArrayHasKey('oneOf', $nestedProp);
		self::assertArrayHasKey('nullable', $nestedProp);
		self::assertTrue($nestedProp['nullable']);
	}

	#[Test]
	public function schemaObjectWithNullableRefGeneratesCorrectJson(): void
	{
		$schema = new SchemaObject(ref: 'TestEnum', nullable: true);

		$json = json_encode($schema);
		assert($json !== false);
		$data = json_decode($json, true);

		// Must have oneOf wrapper
		self::assertArrayHasKey('oneOf', $data);
		self::assertArrayHasKey('nullable', $data);
		self::assertTrue($data['nullable']);
		self::assertCount(1, $data['oneOf']);
		self::assertSame('#/components/schemas/TestEnum', $data['oneOf'][0]['$ref']);
	}

}

// Test classes

enum TestCategory: string
{

	case Active = 'active';

	case Inactive = 'inactive';

}

class DtoWithNullableEnum
{

	public TestCategory|null $category = null;

}

class DtoWithRequiredEnum
{

	public TestCategory $category;

}

class NestedDto
{

	public string $name;

}

class DtoWithNullableClass
{

	public NestedDto|null $nested = null;

}
