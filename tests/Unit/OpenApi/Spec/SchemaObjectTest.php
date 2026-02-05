<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\SchemaObject;

final class SchemaObjectTest extends TestCase
{

	#[Test]
	public function refWithoutNullable(): void
	{
		$schema = new SchemaObject(ref: 'UserDto');

		$result = $schema->jsonSerialize();

		self::assertSame(['$ref' => '#/components/schemas/UserDto'], $result);
	}

	#[Test]
	public function refWithNullableUsesOneOfWrapper(): void
	{
		$schema = new SchemaObject(ref: 'UserDto', nullable: true);

		$result = $schema->jsonSerialize();

		self::assertSame([
			'oneOf' => [['$ref' => '#/components/schemas/UserDto']],
			'nullable' => true,
		], $result);
	}

	#[Test]
	public function refWithNullableFalseReturnsPlainRef(): void
	{
		$schema = new SchemaObject(ref: 'UserDto', nullable: false);

		$result = $schema->jsonSerialize();

		self::assertSame(['$ref' => '#/components/schemas/UserDto'], $result);
	}

	#[Test]
	public function simpleTypeWithNullable(): void
	{
		$schema = new SchemaObject(type: 'string', nullable: true);

		$result = $schema->jsonSerialize();

		self::assertSame([
			'type' => 'string',
			'nullable' => true,
		], $result);
	}

	#[Test]
	public function enumSchema(): void
	{
		$schema = new SchemaObject(type: 'string', enum: ['active', 'inactive']);

		$result = $schema->jsonSerialize();

		self::assertSame([
			'type' => 'string',
			'enum' => ['active', 'inactive'],
		], $result);
	}

	#[Test]
	public function objectWithProperties(): void
	{
		$schema = new SchemaObject(
			type: 'object',
			properties: [
				'name' => new SchemaObject(type: 'string'),
				'age' => new SchemaObject(type: 'integer'),
			],
			required: ['name'],
		);

		$result = $schema->jsonSerialize();

		self::assertSame('object', $result['type']);
		self::assertArrayHasKey('properties', $result);
		self::assertSame(['name'], $result['required']);
	}

	#[Test]
	public function arrayWithItems(): void
	{
		$schema = new SchemaObject(
			type: 'array',
			items: new SchemaObject(type: 'string'),
		);

		$result = $schema->jsonSerialize();

		self::assertSame('array', $result['type']);
		self::assertArrayHasKey('items', $result);
	}

	#[Test]
	public function numericConstraints(): void
	{
		$schema = new SchemaObject(
			type: 'integer',
			minimum: 0,
			maximum: 100,
		);

		$result = $schema->jsonSerialize();

		self::assertSame(0, $result['minimum']);
		self::assertSame(100, $result['maximum']);
	}

	#[Test]
	public function stringConstraints(): void
	{
		$schema = new SchemaObject(
			type: 'string',
			minLength: 1,
			maxLength: 255,
			pattern: '^[a-z]+$',
		);

		$result = $schema->jsonSerialize();

		self::assertSame(1, $result['minLength']);
		self::assertSame(255, $result['maxLength']);
		self::assertSame('^[a-z]+$', $result['pattern']);
	}

	#[Test]
	public function oneOfComposition(): void
	{
		$schema = new SchemaObject(
			oneOf: [
				new SchemaObject(ref: 'Cat'),
				new SchemaObject(ref: 'Dog'),
			],
		);

		$result = $schema->jsonSerialize();

		self::assertArrayHasKey('oneOf', $result);
		self::assertCount(2, $result['oneOf']);
	}

	#[Test]
	public function allOfComposition(): void
	{
		$schema = new SchemaObject(
			allOf: [
				new SchemaObject(ref: 'BaseEntity'),
				new SchemaObject(
					type: 'object',
					properties: ['extra' => new SchemaObject(type: 'string')],
				),
			],
		);

		$result = $schema->jsonSerialize();

		self::assertArrayHasKey('allOf', $result);
		self::assertCount(2, $result['allOf']);
	}

	#[Test]
	public function metadataFields(): void
	{
		$schema = new SchemaObject(
			type: 'string',
			title: 'User Name',
			description: 'The name of the user',
			example: 'John',
			deprecated: true,
		);

		$result = $schema->jsonSerialize();

		self::assertSame('User Name', $result['title']);
		self::assertSame('The name of the user', $result['description']);
		self::assertSame('John', $result['example']);
		self::assertTrue($result['deprecated']);
	}

	#[Test]
	public function omitsNullValues(): void
	{
		$schema = new SchemaObject(type: 'string');

		$result = $schema->jsonSerialize();

		self::assertArrayNotHasKey('format', $result);
		self::assertArrayNotHasKey('description', $result);
		self::assertArrayNotHasKey('nullable', $result);
	}

	#[Test]
	public function omitsEmptyRequiredArray(): void
	{
		$schema = new SchemaObject(
			type: 'object',
			required: [],
		);

		$result = $schema->jsonSerialize();

		self::assertArrayNotHasKey('required', $result);
	}

	#[Test]
	public function refWithNullableAndDescriptionPreservesDescription(): void
	{
		$schema = new SchemaObject(
			ref: 'CountryCode',
			description: 'The country code',
			nullable: true,
		);

		$result = $schema->jsonSerialize();

		self::assertSame([
			'oneOf' => [['$ref' => '#/components/schemas/CountryCode']],
			'nullable' => true,
			'description' => 'The country code',
		], $result);
	}

	#[Test]
	public function refWithDescriptionPreservesDescription(): void
	{
		$schema = new SchemaObject(
			ref: 'CountryCode',
			description: 'The country code',
		);

		$result = $schema->jsonSerialize();

		self::assertSame([
			'$ref' => '#/components/schemas/CountryCode',
			'description' => 'The country code',
		], $result);
	}

	#[Test]
	public function refWithNullableAndAllMetadata(): void
	{
		$schema = new SchemaObject(
			ref: 'StatusEnum',
			title: 'Status',
			description: 'Current status',
			example: 'active',
			nullable: true,
			deprecated: true,
			readOnly: true,
		);

		$result = $schema->jsonSerialize();

		self::assertArrayHasKey('oneOf', $result);
		self::assertTrue($result['nullable']);
		self::assertSame('Current status', $result['description']);
		self::assertSame('active', $result['example']);
		self::assertTrue($result['deprecated']);
		self::assertTrue($result['readOnly']);
		self::assertSame('Status', $result['title']);
	}

}
