<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;

class BackedEnumTypeNormalizer extends AbstractTypeNormalizer
{

	/**
	 * @template T of \BackedEnum
	 * @param class-string<T> $originType
	 * @return T
	 */
	public function denormalize(mixed $value, mixed $originType = null): \BackedEnum
	{

		/** @var T $enumClass */
		$enumClass = $originType;

		foreach ($enumClass::cases() as $case) {
			if (
				$case->value === $value
				|| (string) $case->value === (string) $value
				|| $case->name === $value
				|| $case->name === (string) $value
			) {
				return $case;
			}
		}

		throw new InvalidArgumentTypeException(
			self::class,
			'Invalid value for these enum. Possible values are: ' . implode(', ', array_map(static fn ($case) => $case->value, $enumClass::cases())),
			value: $value,
		);
	}

	public function normalize(mixed $value): mixed
	{
		return $value->value;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return [\BackedEnum::class];
	}

}
