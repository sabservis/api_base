<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use JsonException;
use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;

class ArrayTypeNormalizer extends AbstractTypeNormalizer
{

	public function denormalize(mixed $value): array
	{
		if (is_array($value)) {
			return $value;
		}

		try {
			return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			// nothing
		}

		$val = @unserialize($value);

		if (is_array($val)) {
			return $val;
		}

		throw new InvalidArgumentTypeException(self::class, 'The value is not a valid array.', value: $value);
	}

	public function normalize(array $value): array
	{
		return $value;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return ['array'];
	}

}
