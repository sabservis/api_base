<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use function array_key_exists;
use function func_num_args;
use function is_array;

final class Arrays
{

	/**
	 * Returns item from array. If it does not exist, it throws an exception, unless a default value is set.
	 *
	 * @template T
	 * @param array<T> $array
	 * @param array-key|array<array-key> $key
	 * @param ?T $default
	 * @return ?T
	 * @throws InvalidArgumentException if item does not exist and default value is not provided
	 */
	public static function get(array $array, string|int|array $key, mixed $default = null): mixed
	{
		foreach (is_array($key) ? $key : [$key] as $k) {
			if (!is_array($array) || !array_key_exists($k, $array)) {
				if (func_num_args() < 3) {
					throw new InvalidArgumentException("Missing item '$k'.");
				}

				return $default;
			}

			$array = $array[$k];
		}

		return $array;
	}

}
