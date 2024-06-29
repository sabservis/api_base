<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use function filter_var;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

class BooleanTypeNormalizer extends AbstractTypeNormalizer
{

	public function denormalize(mixed $value): bool
	{
		$val = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

		if ($val === null) {
			throw new InvalidArgumentTypeException(self::class, 'This value is not boolean.', value: $value);
		}

		return $val;
	}

	public function normalize(mixed $value): bool
	{
		return (bool) $value;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return ['bool', 'boolean'];
	}

}
