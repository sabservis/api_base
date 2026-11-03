<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use JsonException;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use function is_array;
use function is_float;
use function is_string;
use function json_decode;
use function sprintf;
use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

/**
 * json_decode() wrapper that rejects integer literals outside the 64-bit range.
 *
 * PHP silently turns 9223372036854775808 into a float (precision loss); a later cast to int
 * wraps to PHP_INT_MIN or saturates to PHP_INT_MAX - an id or amount is corrupted with a 200.
 * The input is decoded twice: with JSON_BIGINT_AS_STRING an overflowing literal becomes a string
 * where the plain decode has a float, which pinpoints it. Float literals (1e19) and numeric strings
 * are identical in both decodes and pass through.
 */
final class JsonDecoder
{

	/**
	 * @param int<1, max> $depth
	 * @throws JsonException When the JSON is malformed or exceeds $depth
	 * @throws ClientErrorException When an integer literal does not fit into 64 bits
	 */
	public static function decode(string $json, int $depth): mixed
	{
		$decoded = json_decode($json, true, $depth, JSON_THROW_ON_ERROR);
		$exact = json_decode($json, true, $depth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

		$path = self::findOverflow($decoded, $exact, '$');

		if ($path !== null) {
			throw new ClientErrorException(sprintf(ErrorMessages::JSON_INTEGER_OUT_OF_RANGE, $path), 400);
		}

		return $decoded;
	}

	/**
	 * Path ("items.1.id") of the first value that is a float in the plain decode but a string
	 * with JSON_BIGINT_AS_STRING, or null when there is none.
	 */
	private static function findOverflow(mixed $decoded, mixed $exact, string $path): string|null
	{
		if (is_array($decoded) && is_array($exact)) {
			foreach ($decoded as $key => $value) {
				$childPath = $path === '$' ? (string) $key : $path . '.' . $key;
				$found = self::findOverflow($value, $exact[$key] ?? null, $childPath);

				if ($found !== null) {
					return $found;
				}
			}

			return null;
		}

		return is_float($decoded) && is_string($exact) ? $path : null;
	}

}
