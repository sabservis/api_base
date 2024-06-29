<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function str_replace;

class FloatTypeNormalizer extends AbstractTypeNormalizer
{

	public function denormalize(mixed $value): float|null
	{
		if (is_string($value)) {
			$value = str_replace(',', '.', $value); // Accept also comma as decimal separator
		}

		if (
			is_float($value)
			|| is_int($value)
			|| (
				is_string($value)
				&& preg_match('#^[+-]?[0-9]*[.]?[0-9]+\z#', $value) === 1
			)
		) {
			return (float) $value;
		}

		throw new InvalidArgumentTypeException(self::class, 'This value is not float number.', value: $value);
	}

	public function normalize(mixed $value): float
	{
		return (float) $value;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return ['float', 'double', 'decimal', 'number'];
	}

}
