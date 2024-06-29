<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use UnitEnum as T;

class UnitEnumTypeNormalizer extends AbstractTypeNormalizer
{

	/**
	 * @template T of \UnitEnum
	 * @param class-string<T>|null $originType
	 * @return T
	 */
	public function denormalize(mixed $value, mixed $originType = null): \UnitEnum
	{
		/** @var T $enumClass */
		$enumClass = $originType;

		foreach ($enumClass::cases() as $case) {
			if ($case->name === $value || $case->name === (string) $value
			) {
				return $case;
			}
		}

		throw new InvalidArgumentTypeException(
			self::class,
			'Invalid value for these enum. Possible values are: ' . implode(', ', array_map(static fn ($case) => $case->name, $enumClass::cases())),
			value: $value,
		);
	}

	public function normalize(\UnitEnum $value): string
	{
		return $value->name;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return [\UnitEnum::class];
	}

}
