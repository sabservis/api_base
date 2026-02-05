<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Discriminator;

final class DiscriminatorTest extends TestCase
{

	#[Test]
	public function toOpenApiSpecReturnsEmptyForNoProperties(): void
	{
		$discriminator = new Discriminator();

		$spec = $discriminator->toOpenApiSpec();

		self::assertSame([], $spec);
	}

	#[Test]
	public function toOpenApiSpecReturnsPropertyName(): void
	{
		$discriminator = new Discriminator(propertyName: 'type');

		$spec = $discriminator->toOpenApiSpec();

		self::assertSame(['propertyName' => 'type'], $spec);
	}

	#[Test]
	public function toOpenApiSpecReturnsMapping(): void
	{
		$discriminator = new Discriminator(
			propertyName: 'petType',
			mapping: [
				'dog' => 'Dog',
				'cat' => 'Cat',
			],
		);

		$spec = $discriminator->toOpenApiSpec();

		self::assertSame('petType', $spec['propertyName']);
		self::assertSame(['dog' => 'Dog', 'cat' => 'Cat'], $spec['mapping']);
	}

	#[Test]
	public function toOpenApiSpecIgnoresEmptyMapping(): void
	{
		$discriminator = new Discriminator(
			propertyName: 'type',
			mapping: [],
		);

		$spec = $discriminator->toOpenApiSpec();

		self::assertArrayHasKey('propertyName', $spec);
		self::assertArrayNotHasKey('mapping', $spec);
	}

	#[Test]
	public function propertyNameCanBeAccessed(): void
	{
		$discriminator = new Discriminator(propertyName: 'objectType');

		self::assertSame('objectType', $discriminator->propertyName);
	}

	#[Test]
	public function mappingCanBeAccessed(): void
	{
		$mapping = ['a' => 'SchemaA', 'b' => 'SchemaB'];
		$discriminator = new Discriminator(mapping: $mapping);

		self::assertSame($mapping, $discriminator->mapping);
	}

}
