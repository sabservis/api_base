<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use function is_int;
use function is_string;
use function preg_match;

class IntegerTypeNormalizer extends AbstractTypeNormalizer
{

	public function denormalize(mixed $value): int
	{
		if (is_int($value) || (is_string($value) && preg_match('#^[+-]?[0-9]+\z#', $value) === 1)) {
			return (int) $value;
		}

		throw new InvalidArgumentTypeException(self::class, 'This value is not integer.', value: $value);
	}

	public function normalize(mixed $value): int
	{
		return (int) $value;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return ['int', 'integer'];
	}

}
