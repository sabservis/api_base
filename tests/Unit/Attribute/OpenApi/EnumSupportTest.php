<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\EnumSupport;

final class EnumSupportTest extends TestCase
{

	#[Test]
	public function resolveEnumReturnsArrayAsIs(): void
	{
		$helper = $this->createEnumHelper();

		$result = $helper->testResolveEnum(['active', 'inactive', 'pending']);

		self::assertSame(['active', 'inactive', 'pending'], $result);
	}

	#[Test]
	public function resolveEnumReturnsEmptyArrayForNull(): void
	{
		$helper = $this->createEnumHelper();

		$result = $helper->testResolveEnum(null);

		self::assertSame([], $result);
	}

	#[Test]
	public function resolveEnumResolvesBackedEnum(): void
	{
		$helper = $this->createEnumHelper();

		$result = $helper->testResolveEnum(TestBackedStatus::class);

		self::assertSame(['active', 'inactive', 'pending'], $result);
	}

	#[Test]
	public function resolveEnumResolvesIntBackedEnum(): void
	{
		$helper = $this->createEnumHelper();

		$result = $helper->testResolveEnum(TestIntPriority::class);

		self::assertSame([1, 2, 3], $result);
	}

	#[Test]
	public function resolveEnumResolvesUnitEnum(): void
	{
		$helper = $this->createEnumHelper();

		$result = $helper->testResolveEnum(TestUnitStatus::class);

		self::assertSame(['Active', 'Inactive', 'Pending'], $result);
	}

	#[Test]
	public function resolveEnumReturnsEmptyArrayForNonExistentClass(): void
	{
		$helper = $this->createEnumHelper();

		$result = $helper->testResolveEnum('NonExistent\\Enum\\Class');

		self::assertSame([], $result);
	}

	#[Test]
	public function resolveEnumCachesResult(): void
	{
		$helper = $this->createEnumHelper();

		$result1 = $helper->testResolveEnum(['a', 'b']);
		$result2 = $helper->testResolveEnum(['c', 'd']); // Should return cached result

		self::assertSame(['a', 'b'], $result1);
		self::assertSame(['a', 'b'], $result2); // Cached!
	}

	/**
	 * @return object
	 */
	private function createEnumHelper(): object
	{
		return new class {

			use EnumSupport;

			/**
			 * @param class-string|\UnitEnum|array<mixed>|string|null $enum
			 * @return array<mixed>
			 */
			public function testResolveEnum(string|array|null $enum): array
			{
				return $this->doResolveEnum($enum);
			}

		};
	}

}

enum TestBackedStatus: string
{

	case Active = 'active';
	case Inactive = 'inactive';
	case Pending = 'pending';

}

enum TestIntPriority: int
{

	case Low = 1;
	case Medium = 2;
	case High = 3;

}

enum TestUnitStatus
{

	case Active;
	case Inactive;
	case Pending;

}
