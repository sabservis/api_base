<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use DateTimeImmutable;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Throwable;
use function sprintf;

/**
 * Utility for parsing datetime strings with fallback formats.
 */
final class DateTimeParser
{

	/**
	 * Default datetime formats to try (in order of preference).
	 */
	public const DEFAULT_FORMATS = [
		'Y-m-d\TH:i:sP', // ISO 8601 with timezone
		'Y-m-d\TH:i:s', // ISO 8601 without timezone
		'Y-m-d', // Date only
	];

	/**
	 * Parse datetime string with fallback formats.
	 *
	 * Tries each format in order, falling back to DateTimeImmutable constructor
	 * as a last resort.
	 *
	 * @param list<string> $formats Formats to try (in order of preference)
	 * @throws InvalidArgumentException When parsing fails with all formats
	 */
	public static function parse(string $value, array $formats = self::DEFAULT_FORMATS): DateTimeImmutable
	{
		// Try each format in order
		foreach ($formats as $format) {
			$result = DateTimeImmutable::createFromFormat($format, $value);

			if ($result !== false) {
				return $result;
			}
		}

		// Fallback to constructor parsing
		try {
			return new DateTimeImmutable($value);
		} catch (Throwable $e) {
			throw new InvalidArgumentException(
				sprintf('Unable to parse datetime value "%s"', $value),
				previous: $e,
			);
		}
	}

}
