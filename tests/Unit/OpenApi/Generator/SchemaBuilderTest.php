<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\OpenApi\Generator\SchemaBuilder;

final class SchemaBuilderTest extends TestCase
{

	#[Test]
	public function buildsSimpleClassSchema(): void
	{
		$builder = new SchemaBuilder();

		$schemaName = $builder->registerClass(SimpleDto::class);
		$schemas = $builder->getSchemas();

		self::assertSame('SimpleDto', $schemaName);
		self::assertArrayHasKey('SimpleDto', $schemas);

		$schema = $schemas['SimpleDto'];
		self::assertSame('object', $schema->type);
		self::assertArrayHasKey('name', $schema->properties);
		self::assertArrayHasKey('age', $schema->properties);
	}

	#[Test]
	public function buildsSchemaWithPropertyTypes(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(TypedDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['TypedDto'];

		// String property
		self::assertSame('string', $schema->properties['name']->type);

		// Integer property
		self::assertSame('integer', $schema->properties['count']->type);

		// Float property
		self::assertSame('number', $schema->properties['price']->type);
		self::assertSame('double', $schema->properties['price']->format);

		// Boolean property
		self::assertSame('boolean', $schema->properties['active']->type);
	}

	#[Test]
	public function buildsSchemaWithRequired(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(RequiredDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['RequiredDto'];

		// Non-nullable property should be required
		self::assertContains('name', $schema->required);

		// Nullable property should not be required
		self::assertNotContains('description', $schema->required ?? []);
	}

	#[Test]
	public function buildsSchemaWithNestedClass(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(ParentDto::class);
		$schemas = $builder->getSchemas();

		// Both parent and child should be registered
		self::assertArrayHasKey('ParentDto', $schemas);
		self::assertArrayHasKey('ChildDto', $schemas);

		// Parent should reference child
		$parentSchema = $schemas['ParentDto'];
		self::assertSame('ChildDto', $parentSchema->properties['child']->ref);
	}

	#[Test]
	public function handlesCircularReferences(): void
	{
		$builder = new SchemaBuilder();

		// Should not cause infinite loop
		$builder->registerClass(CircularA::class);
		$schemas = $builder->getSchemas();

		self::assertArrayHasKey('CircularA', $schemas);
		self::assertArrayHasKey('CircularB', $schemas);
	}

	#[Test]
	public function buildsEnumSchema(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(TestStatus::class);
		$schemas = $builder->getSchemas();

		self::assertArrayHasKey('TestStatus', $schemas);
		$schema = $schemas['TestStatus'];

		self::assertSame('string', $schema->type);
		self::assertContains('pending', $schema->enum);
		self::assertContains('active', $schema->enum);
		self::assertContains('completed', $schema->enum);
	}

	#[Test]
	public function buildsIntBackedEnumSchema(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(TestPriority::class);
		$schemas = $builder->getSchemas();

		$schema = $schemas['TestPriority'];

		self::assertSame('integer', $schema->type);
		self::assertContains(1, $schema->enum);
		self::assertContains(2, $schema->enum);
		self::assertContains(3, $schema->enum);
	}

	#[Test]
	public function getReferencedReturnsSchemaObject(): void
	{
		$builder = new SchemaBuilder();

		$ref = $builder->getReference(SimpleDto::class);

		self::assertSame('SimpleDto', $ref->ref);
	}

	#[Test]
	public function hasSchemaReturnsTrueForRegistered(): void
	{
		$builder = new SchemaBuilder();

		self::assertFalse($builder->hasSchema('SimpleDto'));

		$builder->registerClass(SimpleDto::class);

		self::assertTrue($builder->hasSchema('SimpleDto'));
	}

	#[Test]
	public function schemaFilterExcludesClasses(): void
	{
		$filter = fn (string $className): bool => !str_contains($className, 'Excluded');

		$builder = new SchemaBuilder($filter);

		$builder->registerClass(ExcludedDto::class);
		$schemas = $builder->getSchemas();

		self::assertArrayNotHasKey('ExcludedDto', $schemas);
	}

	#[Test]
	public function addSchemaManually(): void
	{
		$builder = new SchemaBuilder();

		$schema = new \Sabservis\Api\OpenApi\Spec\SchemaObject(type: 'string');
		$builder->addSchema('CustomSchema', $schema);

		self::assertTrue($builder->hasSchema('CustomSchema'));
		self::assertSame($schema, $builder->getSchemas()['CustomSchema']);
	}

	#[Test]
	public function buildsSchemaWithPropertyAttributes(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(AttributedDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['AttributedDto'];

		// Check property with custom name
		self::assertArrayHasKey('user_name', $schema->properties);

		// Check property with description
		self::assertSame('The user email address', $schema->properties['email']->description);

		// Check property with format
		self::assertSame('email', $schema->properties['email']->format);
	}

	#[Test]
	public function buildsSchemaWithDateTimeProperty(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(DateTimeDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['DateTimeDto'];

		// DateTimeImmutable is a class, so it gets registered as a reference
		// The schema builder registers it and creates a reference
		self::assertSame('DateTimeImmutable', $schema->properties['createdAt']->ref);
	}

	#[Test]
	public function reusesAlreadyRegisteredSchema(): void
	{
		$builder = new SchemaBuilder();

		$name1 = $builder->registerClass(SimpleDto::class);
		$name2 = $builder->registerClass(SimpleDto::class);

		self::assertSame($name1, $name2);
		self::assertCount(1, $builder->getSchemas());
	}

}

// Test DTOs

class SimpleDto
{

	public string $name;

	public int $age;

}

class TypedDto
{

	public string $name;

	public int $count;

	public float $price;

	public bool $active;

}

class RequiredDto
{

	public string $name;

	public ?string $description = null;

}

class ParentDto
{

	public string $title;

	public ChildDto $child;

}

class ChildDto
{

	public string $value;

}

class CircularA
{

	public string $name;

	public CircularB $b;

}

class CircularB
{

	public string $name;

	public CircularA $a;

}

enum TestStatus: string
{

	case Pending = 'pending';
	case Active = 'active';
	case Completed = 'completed';

}

enum TestPriority: int
{

	case Low = 1;
	case Medium = 2;
	case High = 3;

}

class ExcludedDto
{

	public string $name;

}

class AttributedDto
{

	#[Property(property: 'user_name')]
	public string $name;

	#[Property(description: 'The user email address', format: 'email')]
	public string $email;

}

class DateTimeDto
{

	public \DateTimeImmutable $createdAt;

}
