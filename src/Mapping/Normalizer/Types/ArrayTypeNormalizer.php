<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use JsonException;
use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use function is_array;
use function json_decode;
use function unserialize;
use const JSON_THROW_ON_ERROR;

class ArrayTypeNormalizer extends AbstractTypeNormalizer
{

	/**
	 * @return array<mixed>
	 */
	public function denormalize(mixed $value): array
	{
		if (is_array($value)) {
			return $value;
		}

		try {
			$val = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
			if (is_array($val)) {
				return $val;
			}
		} catch (JsonException) {
			// nothing
		}

		$val = @unserialize((string) $value);

		if (is_array($val)) {
			return $val;
		}

		throw new InvalidArgumentTypeException(self::class, 'The value is not a valid array.', value: $value);
	}

	/**
	 * @return array<mixed>
	 */
	public function normalize(mixed $value): array
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
