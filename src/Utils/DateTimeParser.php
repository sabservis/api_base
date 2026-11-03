<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use DateTimeImmutable;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use function sprintf;

/**
 * Utility for parsing datetime strings against an explicit list of formats.
 */
final class DateTimeParser
{

	/**
	 * Default datetime formats to try (in order of preference).
	 */
	public const DEFAULT_FORMATS = [
		'Y-m-d\TH:i:sP', // ISO 8601 with timezone
		'Y-m-d\TH:i:s', // ISO 8601 without timezone
		'Y-m-d\TH:i', // HTML <input type="datetime-local"> (no seconds)
		'Y-m-d H:i:s', // SQL / form style with space separator
		'!Y-m-d', // Date only - "!" = midnight (without it time is taken from now)
	];

	/**
	 * Parse datetime string, trying each format in order.
	 *
	 * Strict on purpose: no DateTimeImmutable constructor fallback (which accepts "",
	 * "now", "tomorrow", "+1 day"), and values that only parse with a warning
	 * (2024-02-30 → 2024-03-01, 25:00 → next day) are rejected as well.
	 *
	 * @param list<string> $formats Formats to try (in order of preference)
	 * @throws InvalidArgumentException When the value matches none of the formats exactly
	 */
	public static function parse(string $value, array $formats = self::DEFAULT_FORMATS): DateTimeImmutable
	{
		foreach ($formats as $format) {
			$result = DateTimeImmutable::createFromFormat($format, $value);

			if ($result === false) {
				continue;
			}

			// PHP 8.1 always returns an array, 8.2+ returns false when there are no errors
			$errors = DateTimeImmutable::getLastErrors();

			if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
				continue;
			}

			return $result;
		}

		throw new InvalidArgumentException(sprintf('Unable to parse datetime value "%s"', $value));
	}

}
