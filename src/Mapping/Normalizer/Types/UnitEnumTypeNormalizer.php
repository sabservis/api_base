<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use RuntimeException;
use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use UnitEnum;
use function array_map;
use function implode;

class UnitEnumTypeNormalizer extends AbstractTypeNormalizer
{

	/**
	 * @template T of UnitEnum
	 * @param class-string<T> $enumClass
	 * @return T
	 */
	public function denormalize(mixed $value, string|null $enumClass = null): mixed
	{
		if ($enumClass === null) {
			throw new RuntimeException('Enum class must be provided.');
		}

		foreach ($enumClass::cases() as $case) {
			if ($case->name === $value || $case->name === (string) $value) {
				return $case;
			}
		}

		throw new InvalidArgumentTypeException(
			self::class,
			'Invalid value for this enum. Possible values are: ' . implode(
				', ',
				array_map(static fn ($case) => $case->name, $enumClass::cases()),
			),
			value: $value,
		);
	}

	/**
	 * @param UnitEnum $value
	 */
	public function normalize(mixed $value): string
	{
		return $value->name;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return [UnitEnum::class];
	}

}
