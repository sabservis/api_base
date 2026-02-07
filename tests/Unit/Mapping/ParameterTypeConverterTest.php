<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Mapping\ParameterTypeConverter;
use Sabservis\Api\Schema\EndpointParameter;

enum TestStatus: int
{

	case Active = 1;

	case Inactive = 0;

}

enum TestColor: string
{

	case Red = 'red';

	case Green = 'green';

	case Blue = 'blue';

}

enum TestPriority
{

	case Low;

	case Medium;

	case High;

}

enum TestRole: string
{

	case Admin = 'administrator';

	case User = 'regular_user';

	case Guest = 'guest_visitor';

}

final class ParameterTypeConverterTest extends TestCase
{

	private ParameterTypeConverter $converter;

	protected function setUp(): void
	{
		$this->converter = new ParameterTypeConverter();
	}

	// === Integer conversion ===

	#[Test]
	public function convertInteger(): void
	{
		$param = new EndpointParameter('id', 'int');

		$result = $this->converter->convert('42', 'int', $param);

		self::assertSame(42, $result);
	}

	#[Test]
	public function convertIntegerAlias(): void
	{
		$param = new EndpointParameter('id', 'integer');

		$result = $this->converter->convert('42', 'integer', $param);

		self::assertSame(42, $result);
	}

	#[Test]
	public function convertInvalidIntegerThrows(): void
	{
		$param = new EndpointParameter('id', 'int');

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Parameter 'id': invalid value 'abc'. Expected integer.");

		$this->converter->convert('abc', 'int', $param);
	}

	#[Test]
	public function convertNegativeInteger(): void
	{
		$param = new EndpointParameter('offset', 'int');

		$result = $this->converter->convert('-10', 'int', $param);

		self::assertSame(-10, $result);
	}

	// === Float conversion ===

	#[Test]
	public function convertFloat(): void
	{
		$param = new EndpointParameter('price', 'float');

		$result = $this->converter->convert('19.99', 'float', $param);

		self::assertSame(19.99, $result);
	}

	#[Test]
	public function convertFloatAlias(): void
	{
		$param = new EndpointParameter('price', 'double');

		$result = $this->converter->convert('3.14', 'double', $param);

		self::assertSame(3.14, $result);
	}

	#[Test]
	public function convertInvalidFloatThrows(): void
	{
		$param = new EndpointParameter('price', 'float');

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Parameter 'price': invalid value 'abc'. Expected number (e.g. 3.14).");

		$this->converter->convert('abc', 'float', $param);
	}

	// === Boolean conversion ===

	#[Test]
	#[DataProvider('booleanTrueProvider')]
	public function convertBooleanTrue(string $value): void
	{
		$param = new EndpointParameter('active', 'bool');

		$result = $this->converter->convert($value, 'bool', $param);

		self::assertTrue($result);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function booleanTrueProvider(): array
	{
		return [
			'true' => ['true'],
			'1' => ['1'],
			'yes' => ['yes'],
			'on' => ['on'],
		];
	}

	#[Test]
	#[DataProvider('booleanFalseProvider')]
	public function convertBooleanFalse(string $value): void
	{
		$param = new EndpointParameter('active', 'bool');

		$result = $this->converter->convert($value, 'bool', $param);

		self::assertFalse($result);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function booleanFalseProvider(): array
	{
		return [
			'false' => ['false'],
			'0' => ['0'],
			'no' => ['no'],
			'off' => ['off'],
		];
	}

	#[Test]
	#[DataProvider('invalidBooleanProvider')]
	public function convertBooleanThrowsForInvalidValue(string $value): void
	{
		$param = new EndpointParameter('active', 'bool');

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage(
			"Parameter 'active': invalid value '$value'. Expected boolean (true/false, 1/0, yes/no, on/off).",
		);

		$this->converter->convert($value, 'bool', $param);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidBooleanProvider(): array
	{
		return [
			'invalid' => ['invalid'],
			'typo ture' => ['ture'],
			'typo fasle' => ['fasle'],
			'random string' => ['abc'],
			'number 2' => ['2'],
			'yess typo' => ['yess'],
		];
	}

	// === String conversion ===

	#[Test]
	public function convertString(): void
	{
		$param = new EndpointParameter('name', 'string');

		$result = $this->converter->convert('hello', 'string', $param);

		self::assertSame('hello', $result);
	}

	#[Test]
	public function convertIntegerToString(): void
	{
		$param = new EndpointParameter('code', 'string');

		$result = $this->converter->convert(123, 'string', $param);

		self::assertSame('123', $result);
	}

	// === Array conversion ===

	#[Test]
	public function convertArray(): void
	{
		$param = new EndpointParameter('tags', 'array');

		$result = $this->converter->convert(['a', 'b'], 'array', $param);

		self::assertSame(['a', 'b'], $result);
	}

	#[Test]
	public function convertStringToArray(): void
	{
		$param = new EndpointParameter('tags', 'array');

		$result = $this->converter->convert('single', 'array', $param);

		self::assertSame(['single'], $result);
	}

	// === DateTime conversion ===

	#[Test]
	public function convertDateTimeIso8601(): void
	{
		$param = new EndpointParameter('date', DateTimeInterface::class);

		$result = $this->converter->convert('2024-01-15T10:30:00+01:00', DateTimeInterface::class, $param);

		self::assertInstanceOf(DateTimeImmutable::class, $result);
		self::assertSame('2024-01-15', $result->format('Y-m-d'));
		self::assertSame('10:30:00', $result->format('H:i:s'));
	}

	#[Test]
	public function convertDateTimeWithoutTimezone(): void
	{
		$param = new EndpointParameter('date', DateTimeInterface::class);

		$result = $this->converter->convert('2024-01-15T10:30:00', DateTimeInterface::class, $param);

		self::assertInstanceOf(DateTimeImmutable::class, $result);
		self::assertSame('2024-01-15', $result->format('Y-m-d'));
	}

	#[Test]
	public function convertDateOnly(): void
	{
		$param = new EndpointParameter('date', DateTimeInterface::class);

		$result = $this->converter->convert('2024-01-15', DateTimeInterface::class, $param);

		self::assertInstanceOf(DateTimeImmutable::class, $result);
		self::assertSame('2024-01-15', $result->format('Y-m-d'));
	}

	#[Test]
	public function convertInvalidDateTimeThrows(): void
	{
		$param = new EndpointParameter('date', DateTimeInterface::class);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Parameter 'date': invalid value 'not-a-date'. Expected date/datetime");

		$this->converter->convert('not-a-date', DateTimeInterface::class, $param);
	}

	// === BackedEnum conversion ===

	#[Test]
	public function convertBackedEnumFromInt(): void
	{
		$param = new EndpointParameter('status', TestStatus::class);

		$result = $this->converter->convert('1', TestStatus::class, $param);

		self::assertSame(TestStatus::Active, $result);
	}

	#[Test]
	public function convertBackedEnumFromString(): void
	{
		$param = new EndpointParameter('color', TestColor::class);

		$result = $this->converter->convert('green', TestColor::class, $param);

		self::assertSame(TestColor::Green, $result);
	}

	#[Test]
	public function convertBackedEnumByCaseName(): void
	{
		$param = new EndpointParameter('role', TestRole::class);

		// "Admin" is the case name, "administrator" is the value
		$result = $this->converter->convert('Admin', TestRole::class, $param);

		self::assertSame(TestRole::Admin, $result);
	}

	#[Test]
	public function convertBackedEnumByValueWhenNameAndValueDiffer(): void
	{
		$param = new EndpointParameter('role', TestRole::class);

		// "administrator" is the value, "Admin" is the case name
		$result = $this->converter->convert('administrator', TestRole::class, $param);

		self::assertSame(TestRole::Admin, $result);
	}

	#[Test]
	public function convertBackedEnumPrefersValueOverName(): void
	{
		$param = new EndpointParameter('role', TestRole::class);

		// Both value and name should work, value is checked first
		$resultByValue = $this->converter->convert('regular_user', TestRole::class, $param);
		$resultByName = $this->converter->convert('User', TestRole::class, $param);

		self::assertSame(TestRole::User, $resultByValue);
		self::assertSame(TestRole::User, $resultByName);
	}

	#[Test]
	public function convertInvalidBackedEnumThrows(): void
	{
		$param = new EndpointParameter('color', TestColor::class);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage(
			"Parameter 'color': invalid value 'yellow'. Expected one of: Red (red), Green (green), Blue (blue).",
		);

		$this->converter->convert('yellow', TestColor::class, $param);
	}

	// === UnitEnum conversion ===

	#[Test]
	public function convertUnitEnumByName(): void
	{
		$param = new EndpointParameter('priority', TestPriority::class);

		$result = $this->converter->convert('High', TestPriority::class, $param);

		self::assertSame(TestPriority::High, $result);
	}

	#[Test]
	public function convertInvalidUnitEnumThrows(): void
	{
		$param = new EndpointParameter('priority', TestPriority::class);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage(
			"Parameter 'priority': invalid value 'Critical'. Expected one of: Low, Medium, High.",
		);

		$this->converter->convert('Critical', TestPriority::class, $param);
	}

	// === Null/Empty handling ===

	#[Test]
	public function convertEmptyStringReturnsNull(): void
	{
		$param = new EndpointParameter('id', 'int');

		$result = $this->converter->convert('', 'int', $param);

		self::assertNull($result);
	}

	#[Test]
	public function convertNullReturnsNull(): void
	{
		$param = new EndpointParameter('id', 'int');

		$result = $this->converter->convert(null, 'int', $param);

		self::assertNull($result);
	}

	// === Unknown type passthrough ===

	#[Test]
	public function convertUnknownTypePassesThrough(): void
	{
		$param = new EndpointParameter('data', 'UnknownClass');

		$result = $this->converter->convert('anything', 'UnknownClass', $param);

		self::assertSame('anything', $result);
	}

}
