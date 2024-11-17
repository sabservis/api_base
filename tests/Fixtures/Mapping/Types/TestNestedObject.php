<?php declare(strict_types = 1);

namespace Tests\Fixtures\Mapping\Types;

use DateTimeImmutable;
use Sabservis\Api\Mapping\Entity\Entity;

final class TestNestedObject implements Entity
{

	public int $integer;

	public float $float;

	public string $string;

	public bool $boolean;

	public DateTimeImmutable $dateTime;

	public array $array;

	public TestBackedEnum $backedEnum;

	public TestUnitEnum $unitEnum;

	public self|null $nestedObject;

}
