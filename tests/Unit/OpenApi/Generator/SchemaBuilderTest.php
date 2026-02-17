<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\AdditionalProperties;
use Sabservis\Api\Attribute\OpenApi\Discriminator;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\OpenApi\Generator\SchemaBuilder;
use Sabservis\Api\OpenApi\Spec\SchemaObject;
use function str_contains;

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
		$filter = static fn (string $className): bool => !str_contains($className, 'Excluded');

		$builder = new SchemaBuilder($filter);

		$builder->registerClass(ExcludedDto::class);
		$schemas = $builder->getSchemas();

		self::assertArrayNotHasKey('ExcludedDto', $schemas);
	}

	#[Test]
	public function addSchemaManually(): void
	{
		$builder = new SchemaBuilder();

		$schema = new SchemaObject(type: 'string');
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
	public function buildsSchemaWithPropertyAttributesOnPromotedConstructorParameter(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(PromotedAttributedDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['PromotedAttributedDto'];

		self::assertArrayHasKey('login', $schema->properties);
		self::assertSame('Login name', $schema->properties['login']->description);
	}

	#[Test]
	public function buildsSchemaWithDateTimeProperty(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(DateTimeDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['DateTimeDto'];

		// DateTimeImmutable should be mapped to string with date-time format, not as $ref
		self::assertNull($schema->properties['createdAt']->ref);
		self::assertSame('string', $schema->properties['createdAt']->type);
		self::assertSame('date-time', $schema->properties['createdAt']->format);

		// Should NOT register DateTimeImmutable as a separate schema
		self::assertArrayNotHasKey('DateTimeImmutable', $schemas);
	}

	#[Test]
	public function buildsSchemaWithNullableDateTimeProperty(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(NullableDateTimeDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['NullableDateTimeDto'];

		// Nullable DateTimeImmutable should be string with date-time format and nullable
		self::assertNull($schema->properties['updatedAt']->ref);
		self::assertSame('string', $schema->properties['updatedAt']->type);
		self::assertSame('date-time', $schema->properties['updatedAt']->format);
		self::assertTrue($schema->properties['updatedAt']->nullable);
	}

	#[Test]
	public function buildsSchemaWithDateTimePropertyOverriddenToDate(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(DatePropertyDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['DatePropertyDto'];

		// Property with format: 'date' override should produce string with date format
		self::assertSame('string', $schema->properties['birthDate']->type);
		self::assertSame('date', $schema->properties['birthDate']->format);

		// Property with type: 'date' shorthand should also produce string with date format
		self::assertSame('string', $schema->properties['signedDate']->type);
		self::assertSame('date', $schema->properties['signedDate']->format);
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

	#[Test]
	public function buildsSchemaWithNullableUnionType(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(NullableUnionDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['NullableUnionDto'];

		// Nullable string (string|null) should be a simple nullable type
		self::assertSame('string', $schema->properties['name']->type);
		self::assertTrue($schema->properties['name']->nullable);
	}

	#[Test]
	public function buildsSchemaWithMultipleUnionTypes(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(MultiUnionDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['MultiUnionDto'];

		// int|string should use oneOf
		self::assertNotNull($schema->properties['value']->oneOf);
		self::assertCount(2, $schema->properties['value']->oneOf);
	}

	#[Test]
	public function buildsSchemaWithNullableMultipleUnionTypes(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(NullableMultiUnionDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['NullableMultiUnionDto'];

		// int|string|null should use oneOf with nullable
		self::assertNotNull($schema->properties['value']->oneOf);
		self::assertTrue($schema->properties['value']->nullable);
	}

	#[Test]
	public function buildsSchemaWithItemsComposition(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(ItemsCompositionDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['ItemsCompositionDto'];

		// Array with oneOf items should have items.oneOf
		self::assertArrayHasKey('items', $schema->properties);
		self::assertNotNull($schema->properties['items']->items);
		self::assertNotNull($schema->properties['items']->items->oneOf);
		self::assertCount(2, $schema->properties['items']->items->oneOf);
	}

	#[Test]
	public function buildsSchemaWithOneOfComposition(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(PetDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['PetDto'];

		// Schema with oneOf should have oneOf array
		self::assertNotNull($schema->oneOf);
		self::assertCount(2, $schema->oneOf);
		self::assertNull($schema->type); // Composition schema doesn't have type
	}

	#[Test]
	public function buildsSchemaWithDiscriminator(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(PetDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['PetDto'];

		// Schema with Discriminator should have discriminator object
		self::assertNotNull($schema->discriminator);
		self::assertSame('petType', $schema->discriminator->propertyName);
	}

	#[Test]
	public function buildsSchemaWithDiscriminatorMapping(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(PetWithMappingDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['PetWithMappingDto'];

		// Schema with Discriminator mapping should have proper mapping
		self::assertNotNull($schema->discriminator);
		self::assertSame('type', $schema->discriminator->propertyName);
		self::assertNotNull($schema->discriminator->mapping);
		self::assertArrayHasKey('cat', $schema->discriminator->mapping);
		self::assertArrayHasKey('dog', $schema->discriminator->mapping);
		self::assertStringContainsString('#/components/schemas/', $schema->discriminator->mapping['cat']);
	}

	#[Test]
	public function discriminatorOnlyAddedWithComposition(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(DiscriminatorWithoutCompositionDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['DiscriminatorWithoutCompositionDto'];

		// Discriminator without oneOf/anyOf should NOT have discriminator
		self::assertNull($schema->discriminator);
		self::assertSame('object', $schema->type);
	}

	#[Test]
	public function buildsSchemaWithAnyOfAndDiscriminator(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(AnyOfPetDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['AnyOfPetDto'];

		// anyOf schema with Discriminator should have discriminator
		self::assertNotNull($schema->anyOf);
		self::assertNotNull($schema->discriminator);
		self::assertSame('kind', $schema->discriminator->propertyName);
	}

	#[Test]
	public function discriminatorRegistersReferencedSchemas(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(PetWithMappingDto::class);
		$schemas = $builder->getSchemas();

		// Referenced schemas in mapping should be registered
		self::assertArrayHasKey('CatDto', $schemas);
		self::assertArrayHasKey('DogDto', $schemas);
	}

	#[Test]
	public function nullableEnumPropertyPreservesDescription(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(NullableEnumPropertyDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['NullableEnumPropertyDto'];

		$statusProp = $schema->properties['status'];
		self::assertSame('The country code', $statusProp->description);
		self::assertTrue($statusProp->nullable);
		// Should still reference the enum
		self::assertSame('TestStatus', $statusProp->ref);
	}

	#[Test]
	public function nonNullableEnumPropertyPreservesDescription(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(NullableEnumPropertyDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['NullableEnumPropertyDto'];

		$requiredStatusProp = $schema->properties['requiredStatus'];
		self::assertSame('Non-nullable enum', $requiredStatusProp->description);
		self::assertSame('TestStatus', $requiredStatusProp->ref);
	}

	#[Test]
	public function propertyWithAdditionalPropertiesType(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(AdditionalPropertiesDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['AdditionalPropertiesDto'];

		// metadata should have additionalProperties with type string
		$metadataProp = $schema->properties['metadata'];
		self::assertNotNull($metadataProp->additionalProperties);
		self::assertInstanceOf(SchemaObject::class, $metadataProp->additionalProperties);
		self::assertSame('string', $metadataProp->additionalProperties->type);
	}

	#[Test]
	public function propertyWithAdditionalPropertiesBool(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(AdditionalPropertiesBoolDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['AdditionalPropertiesBoolDto'];

		// strict should have additionalProperties = false (SchemaObject supports bool)
		// But Property attr has AdditionalProperties|null, not bool
		// So we test the typed version
		$metadataProp = $schema->properties['data'];
		self::assertNotNull($metadataProp->additionalProperties);
	}

	#[Test]
	public function propertyWithAdditionalPropertiesRef(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(AdditionalPropertiesRefDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['AdditionalPropertiesRefDto'];

		$itemsProp = $schema->properties['items'];
		self::assertNotNull($itemsProp->additionalProperties);
		self::assertInstanceOf(SchemaObject::class, $itemsProp->additionalProperties);
	}

	#[Test]
	public function nullableClassPropertyPreservesDescription(): void
	{
		$builder = new SchemaBuilder();

		$builder->registerClass(NullableClassPropertyDto::class);
		$schemas = $builder->getSchemas();
		$schema = $schemas['NullableClassPropertyDto'];

		$childProp = $schema->properties['child'];
		self::assertSame('The child object', $childProp->description);
		self::assertTrue($childProp->nullable);
		self::assertSame('ChildDto', $childProp->ref);
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

	public string|null $description = null;

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

class PromotedAttributedDto
{

	public function __construct(
		#[Property(property: 'login', description: 'Login name')]
		public string $username,
	)
	{
	}

}

class DateTimeDto
{

	public DateTimeImmutable $createdAt;

}

class NullableDateTimeDto
{

	public DateTimeImmutable|null $updatedAt = null;

}

class DatePropertyDto
{

	#[Property(format: 'date')]
	public DateTimeImmutable $birthDate;

	#[Property(type: 'date')]
	public DateTimeImmutable $signedDate;

}

class NullableUnionDto
{

	public string|null $name;

}

class MultiUnionDto
{

	public int|string $value;

}

class NullableMultiUnionDto
{

	public int|string|null $value;

}

class ItemsCompositionDto
{

	#[Property(type: 'array', items: new Items(oneOf: [SimpleDto::class, TypedDto::class]))]
	public array $items;

}

// Discriminator test DTOs

class CatDto
{

	public string $petType;

	public string $meow;

}

class DogDto
{

	public string $petType;

	public string $bark;

}

#[Schema(oneOf: [CatDto::class, DogDto::class])]
#[Discriminator(propertyName: 'petType')]
class PetDto
{

}

#[Schema(oneOf: [CatDto::class, DogDto::class])]
#[Discriminator(propertyName: 'type', mapping: ['cat' => CatDto::class, 'dog' => DogDto::class])]
class PetWithMappingDto
{

}

#[Discriminator(propertyName: 'type')]
class DiscriminatorWithoutCompositionDto
{

	public string $name;

}

#[Schema(anyOf: [CatDto::class, DogDto::class])]
#[Discriminator(propertyName: 'kind')]
class AnyOfPetDto
{

}

class NullableEnumPropertyDto
{

	#[Property(description: 'The country code', nullable: true)]
	public TestStatus|null $status = null;

	#[Property(description: 'Non-nullable enum')]
	public TestStatus $requiredStatus;

}

class NullableClassPropertyDto
{

	#[Property(description: 'The child object', nullable: true)]
	public ChildDto|null $child = null;

}

class AdditionalPropertiesDto
{

	#[Property(type: 'object', additionalProperties: new AdditionalProperties(type: 'string'))]
	public array $metadata;

}

class AdditionalPropertiesBoolDto
{

	#[Property(type: 'object', additionalProperties: new AdditionalProperties(type: 'integer'))]
	public array $data;

}

class AdditionalPropertiesRefDto
{

	#[Property(type: 'object', additionalProperties: new AdditionalProperties(ref: ChildDto::class))]
	public array $items;

}
