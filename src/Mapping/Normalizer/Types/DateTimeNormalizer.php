<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;

class DateTimeNormalizer  extends AbstractTypeNormalizer
{

	public const DefaultFormat = 'Y-m-d H:i:s';

	public function denormalize(mixed $value, string|null $format = null, string|\DateTimeZone|null $timeZone = null): \DateTimeImmutable
	{
		if (\is_int($value) || \is_float($value)) {
			$value = match ($format ?? null) {
				'U' => sprintf('%d', $value),
				'U.u' => sprintf('%.6F', $value),
				default => $value, // pokud formát není definován, zůstane původní hodnota
			};
		}

		if (\is_string($value) === false || trim($value) === '') {
			throw new InvalidArgumentTypeException(
				self::class,
				'The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string',
				value: $value,
			);
		}

		$timeZone = $timeZone instanceof \DateTimeZone ? $timeZone :new \DateTimeZone($timeZone ?? date_default_timezone_get());

		if ($format !== null) {
			$resultDateTime = \DateTimeImmutable::createFromFormat($format, $value, $timeZone);

			if ($resultDateTime !== false) {
				return $resultDateTime;
			}

			$dateTimeErrors = \DateTimeImmutable::getLastErrors();

			throw new InvalidArgumentTypeException(
				self::class,
				sprintf(
					'Parsing datetime string "%s" using format "%s" resulted in %d errors: ',
					$value,
					$format,
					$dateTimeErrors['error_count']) . "\n" . implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])),
				value: $value,
			);
		}

		try {
			return new \DateTimeImmutable($value, $timeZone);
		} catch (\DateMalformedStringException) {
			$dateTimeErrors = \DateTimeImmutable::getLastErrors();

			throw new InvalidArgumentTypeException(
				self::class,
				sprintf(
					'Parsing datetime string "%s" resulted in %d errors: ',
					$value,
					$dateTimeErrors['error_count']) . "\n" . implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'])),
				value: $value,
			);
		}
	}

	public function normalize(mixed $value, string|null $format = null, string|\DateTimeZone|null $timeZone = null): string
	{
		if ($value instanceof \DateTimeInterface) {
			$timeZone = $timeZone instanceof \DateTimeZone ? $timeZone :new \DateTimeZone($timeZone ?? date_default_timezone_get());
			$dateTime = $value->setTimezone($timeZone);

			return $dateTime->format($format ?? self::DefaultFormat);
		}

		throw new InvalidArgumentTypeException(
			self::class,
			'The data is not an instance of DateTimeInterface',
			value: $value,
		);
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return [\DateTimeImmutable::class, \DateTime::class, \DateTimeInterface::class, 'date'];
	}

	/**
	 * Formats datetime errors.
	 *
	 * @return array<string>
	 */
	private function formatDateTimeErrors(array $errors): array
	{
		$formattedErrors = [];

		foreach ($errors as $pos => $message) {
			$formattedErrors[] = sprintf('at position %d: %s', $pos, $message);
		}

		return $formattedErrors;
	}

}
