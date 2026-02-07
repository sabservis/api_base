<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Sabservis\Api\Attribute\OpenApi\Property;

final class PropertyTest extends TestCase
{

	#[Test]
	public function propertyAttributeCanBeInstantiatedFromPromotedConstructorParameter(): void
	{
		$constructor = new ReflectionMethod(PropertyAttributeFixture::class, '__construct');
		$attributes = $constructor->getParameters()[0]->getAttributes(Property::class);

		self::assertCount(1, $attributes);
		$attribute = $attributes[0]->newInstance();

		self::assertInstanceOf(Property::class, $attribute);
		self::assertSame('Promoted property description', $attribute->description);
	}

	#[Test]
	public function propertyAttributeCanBeInstantiatedFromProperty(): void
	{
		$property = new ReflectionProperty(PropertyAttributeFixture::class, 'regularProperty');
		$attributes = $property->getAttributes(Property::class);

		self::assertCount(1, $attributes);
		$attribute = $attributes[0]->newInstance();

		self::assertInstanceOf(Property::class, $attribute);
		self::assertSame('Regular property description', $attribute->description);
	}

}

class PropertyAttributeFixture
{

	#[Property(description: 'Regular property description')]
	public string $regularProperty = '';

	public function __construct(
		#[Property(description: 'Promoted property description')]
		public string $promotedProperty,
	)
	{
	}

}
