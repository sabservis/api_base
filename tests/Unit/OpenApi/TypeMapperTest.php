<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\TypeMapper;

final class TypeMapperTest extends TestCase
{

	// =====================
	// phpToOpenApi() tests
	// =====================

	#[Test]
	public function mapsIntToInteger(): void
	{
		$result = TypeMapper::phpToOpenApi('int');

		self::assertSame(['type' => 'integer'], $result);
	}

	#[Test]
	public function mapsIntegerToInteger(): void
	{
		$result = TypeMapper::phpToOpenApi('integer');

		self::assertSame(['type' => 'integer'], $result);
	}

	#[Test]
	public function mapsFloatToNumber(): void
	{
		$result = TypeMapper::phpToOpenApi('float');

		self::assertSame(['type' => 'number', 'format' => 'double'], $result);
	}

	#[Test]
	public function mapsDoubleToNumber(): void
	{
		$result = TypeMapper::phpToOpenApi('double');

		self::assertSame(['type' => 'number', 'format' => 'double'], $result);
	}

	#[Test]
	public function mapsBoolToBoolean(): void
	{
		$result = TypeMapper::phpToOpenApi('bool');

		self::assertSame(['type' => 'boolean'], $result);
	}

	#[Test]
	public function mapsBooleanToBoolean(): void
	{
		$result = TypeMapper::phpToOpenApi('boolean');

		self::assertSame(['type' => 'boolean'], $result);
	}

	#[Test]
	public function mapsStringToString(): void
	{
		$result = TypeMapper::phpToOpenApi('string');

		self::assertSame(['type' => 'string'], $result);
	}

	#[Test]
	public function mapsArrayToArray(): void
	{
		$result = TypeMapper::phpToOpenApi('array');

		self::assertSame(['type' => 'array'], $result);
	}

	#[Test]
	public function mapsDateTimeInterfaceToDateTime(): void
	{
		$result = TypeMapper::phpToOpenApi('DateTimeInterface');

		self::assertSame(['type' => 'string', 'format' => 'date-time'], $result);
	}

	#[Test]
	public function mapsDateTimeToDateTime(): void
	{
		$result = TypeMapper::phpToOpenApi('DateTime');

		self::assertSame(['type' => 'string', 'format' => 'date-time'], $result);
	}

	#[Test]
	public function mapsDateTimeImmutableToDateTime(): void
	{
		$result = TypeMapper::phpToOpenApi('DateTimeImmutable');

		self::assertSame(['type' => 'string', 'format' => 'date-time'], $result);
	}

	#[Test]
	public function mapsDateToDate(): void
	{
		$result = TypeMapper::phpToOpenApi('Date');

		self::assertSame(['type' => 'string', 'format' => 'date'], $result);
	}

	#[Test]
	public function mapsUnknownTypeToObject(): void
	{
		$result = TypeMapper::phpToOpenApi('SomeUnknownType');

		self::assertSame(['type' => 'object'], $result);
	}

	#[Test]
	public function mapsClassNameToObject(): void
	{
		$result = TypeMapper::phpToOpenApi('App\\Entity\\User');

		self::assertSame(['type' => 'object'], $result);
	}

	// =====================
	// isScalarType() tests
	// =====================

	#[Test]
	public function isScalarTypeReturnsTrueForScalars(): void
	{
		self::assertTrue(TypeMapper::isScalarType('string'));
		self::assertTrue(TypeMapper::isScalarType('int'));
		self::assertTrue(TypeMapper::isScalarType('integer'));
		self::assertTrue(TypeMapper::isScalarType('float'));
		self::assertTrue(TypeMapper::isScalarType('double'));
		self::assertTrue(TypeMapper::isScalarType('bool'));
		self::assertTrue(TypeMapper::isScalarType('boolean'));
		self::assertTrue(TypeMapper::isScalarType('array'));
		self::assertTrue(TypeMapper::isScalarType('mixed'));
	}

	#[Test]
	public function isScalarTypeReturnsFalseForNonScalars(): void
	{
		self::assertFalse(TypeMapper::isScalarType('DateTime'));
		self::assertFalse(TypeMapper::isScalarType('DateTimeInterface'));
		self::assertFalse(TypeMapper::isScalarType('object'));
		self::assertFalse(TypeMapper::isScalarType('App\\Entity\\User'));
		self::assertFalse(TypeMapper::isScalarType('SomeClass'));
	}

	// =====================
	// isDateType() tests
	// =====================

	#[Test]
	public function isDateTypeReturnsTrueForDateTypes(): void
	{
		self::assertTrue(TypeMapper::isDateType('DateTimeInterface'));
		self::assertTrue(TypeMapper::isDateType('DateTime'));
		self::assertTrue(TypeMapper::isDateType('DateTimeImmutable'));
		self::assertTrue(TypeMapper::isDateType('Date'));
	}

	#[Test]
	public function isDateTypeReturnsFalseForNonDateTypes(): void
	{
		self::assertFalse(TypeMapper::isDateType('string'));
		self::assertFalse(TypeMapper::isDateType('int'));
		self::assertFalse(TypeMapper::isDateType('Carbon'));
		self::assertFalse(TypeMapper::isDateType('timestamp'));
	}

	// =====================
	// Fully qualified class names (with leading backslash)
	// =====================

	#[Test]
	public function handlesFullyQualifiedDateTimeNames(): void
	{
		// phpToOpenApi should handle \DateTime and \DateTimeImmutable
		self::assertSame(
			['type' => 'string', 'format' => 'date-time'],
			TypeMapper::phpToOpenApi('\DateTimeImmutable'),
		);
		self::assertSame(
			['type' => 'string', 'format' => 'date-time'],
			TypeMapper::phpToOpenApi('\DateTime'),
		);
		self::assertSame(
			['type' => 'string', 'format' => 'date-time'],
			TypeMapper::phpToOpenApi('\DateTimeInterface'),
		);
	}

	#[Test]
	public function isDateTypeHandlesFullyQualifiedNames(): void
	{
		self::assertTrue(TypeMapper::isDateType('\DateTimeImmutable'));
		self::assertTrue(TypeMapper::isDateType('\DateTime'));
		self::assertTrue(TypeMapper::isDateType('\DateTimeInterface'));
	}

}
