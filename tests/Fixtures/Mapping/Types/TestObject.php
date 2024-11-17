<?php declare(strict_types = 1);

namespace Tests\Fixtures\Mapping\Types;

use DateTimeImmutable;
use Sabservis\Api\Mapping\Attribute\DateDenormalizer;
use Sabservis\Api\Mapping\Attribute\DateNormalizer;
use Sabservis\Api\Mapping\Attribute\ObjectArrayDenormalizer;
use Sabservis\Api\Mapping\Entity\Entity;

final class TestObject implements Entity
{

	public int $integer;

	public int $integerWithDefault = 1;

	public int|null $integerNullable;

	public int|null $integerNullableWithDefault = 9;

	public float $float;

	public float $floatWithDefault = 1.1;

	public float|null $floatNullable;

	public float|null $floatNullableWithDefault = 1.1;

	public string $string;

	public string $stringWithDefault = 'string';

	public string|null $stringNullable;

	public string|null $stringNullableWithDefault = 'string';

	public bool $boolean;

	public bool $booleanWithDefault = true;

	public bool|null $booleanNullable;

	public bool|null $booleanNullableWithDefault = true;

	public DateTimeImmutable $dateTime;

	public DateTimeImmutable $dateTimeWithDefault;

	public DateTimeImmutable|null $dateTimeNullable;

	public DateTimeImmutable|null $dateTimeNullableWithDefault;

	#[DateDenormalizer('Y-m-d H:i:s')]
	public DateTimeImmutable $dateTimeWithDenormalizerForYmdHis;

	#[DateDenormalizer('Y-m-d H:i:s', 'America/Chicago')]
	public DateTimeImmutable $dateTimeWithDenormalizerForYmdHisAndWithTimezone;

	#[DateDenormalizer('Y-m-d')]
	public DateTimeImmutable $dateTimeWithDenormalizerForYmd;

	#[DateNormalizer('Y-m-d H:i:s')]
	public DateTimeImmutable $dateTimeWithNormalizerForYmdHis;

	#[DateNormalizer('Y-m-d H:i:sP', 'America/Chicago')]
	public DateTimeImmutable $dateTimeWithNormalizerForYmdHisAndWithTimezone;

	#[DateNormalizer('Y-m-d')]
	public DateTimeImmutable $dateTimeWithNormalizerForYmd;

	public array $array;

	public array $arrayWithDefault = [];

	public array|null $arrayNullable;

	public array|null $arrayNullableWithDefault = ['a', 'b', 'c'];

	public array $arrayWithSerializedInput;

	#[ObjectArrayDenormalizer(TestNestedObject::class)]
	public array $arrayWithObjects;

	public TestBackedEnum $backedEnum;

	public TestBackedEnum $backedEnumWithDefault = TestBackedEnum::FirstCase;

	public TestBackedEnum|null $backedEnumNullable;

	public TestBackedEnum|null $backedEnumNullableWithDefault = TestBackedEnum::FirstCase;

	public TestUnitEnum $unitEnum;

	public TestUnitEnum $unitEnumWithDefault = TestUnitEnum::FirstCase;

	public TestUnitEnum|null $unitEnumNullable;

	public TestUnitEnum|null $unitEnumNullableWithDefault = TestUnitEnum::FirstCase;

	public TestNestedObject $object;

	public TestNestedObject|null $objectNullable;

	public function __construct()
	{
		$this->dateTimeWithDefault = new DateTimeImmutable('2021-01-07 00:00:09');
		$this->dateTimeNullableWithDefault = new DateTimeImmutable('2021-01-03 00:00:00');
	}

}
