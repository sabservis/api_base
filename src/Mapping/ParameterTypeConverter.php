<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionEnum;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Utils\DateTimeParser;
use Throwable;
use UnitEnum;
use function array_map;
use function filter_var;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function sprintf;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

/**
 * Converts parameter values to their declared types.
 *
 * Supports:
 * - Primitives: int, float, bool, string, array
 * - DateTimeInterface (ISO 8601 and common formats)
 * - BackedEnum (by value)
 * - UnitEnum (by name)
 */
class ParameterTypeConverter
{

	/**
	 * Convert a value to the specified type.
	 *
	 * @throws ClientErrorException When conversion fails
	 */
	public function convert(mixed $value, string $type, EndpointParameter $parameter): mixed
	{
		if ($value === '' || $value === null) {
			return null;
		}

		// ?q[]=x for a scalar parameter: (string) would yield "Array" + E_WARNING
		if (is_array($value) && $type !== 'array') {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_INVALID_TYPE,
				$parameter->getName(),
				'array',
				$type,
			));
		}

		try {
			return $this->convertToType($value, $type, $parameter);
		} catch (ClientErrorException $e) {
			throw $e;
		} catch (Throwable) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_INVALID_TYPE,
				$parameter->getName(),
				$value,
				$type,
			));
		}
	}

	protected function convertToType(mixed $value, string $type, EndpointParameter $parameter): mixed
	{
		return match ($type) {
			'int', 'integer' => $this->toInt($value, $parameter),
			'float', 'double' => $this->toFloat($value, $parameter),
			'bool', 'boolean' => $this->toBool($value, $parameter),
			'string' => (string) $value,
			'array' => (array) $value,
			default => $this->convertComplexType($value, $type, $parameter),
		};
	}

	protected function convertComplexType(mixed $value, string $type, EndpointParameter $parameter): mixed
	{
		if (is_subclass_of($type, DateTimeInterface::class) || $type === DateTimeInterface::class) {
			$date = $this->toDateTime($value, $parameter);

			// Mutable \DateTime (or a subclass of either) in the signature - a DateTimeImmutable would TypeError
			if ($type !== DateTimeInterface::class && $type !== DateTimeImmutable::class) {
				/** @var class-string<DateTime|DateTimeImmutable> $type */
				return $type::createFromInterface($date);
			}

			return $date;
		}

		if (is_subclass_of($type, BackedEnum::class)) {
			return $this->toBackedEnum($value, $type, $parameter);
		}

		if (is_subclass_of($type, UnitEnum::class)) {
			return $this->toUnitEnum($value, $type, $parameter);
		}

		return $value;
	}

	protected function toInt(mixed $value, EndpointParameter $parameter): int
	{
		$filtered = filter_var($value, FILTER_VALIDATE_INT);

		if ($filtered === false) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_INVALID_INT,
				$parameter->getName(),
				$value,
			));
		}

		return $filtered;
	}

	protected function toFloat(mixed $value, EndpointParameter $parameter): float
	{
		$filtered = filter_var($value, FILTER_VALIDATE_FLOAT);

		if ($filtered === false) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_INVALID_FLOAT,
				$parameter->getName(),
				$value,
			));
		}

		return $filtered;
	}

	protected function toBool(mixed $value, EndpointParameter $parameter): bool
	{
		$filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

		if ($filtered === null) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_INVALID_BOOL,
				$parameter->getName(),
				$value,
			));
		}

		return $filtered;
	}

	protected function toDateTime(mixed $value, EndpointParameter $parameter): DateTimeImmutable
	{
		if ($parameter->getFormat() === 'date') {
			return $this->toDate((string) $value, $parameter);
		}

		try {
			return DateTimeParser::parse((string) $value);
		} catch (InvalidArgumentException) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_INVALID_DATETIME,
				$parameter->getName(),
				$value,
			));
		}
	}

	/**
	 * format: 'date' accepts only an existing calendar date YYYY-MM-DD, time is midnight.
	 */
	private function toDate(string $value, EndpointParameter $parameter): DateTimeImmutable
	{
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

		if ($date === false || $date->format('Y-m-d') !== $value) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::PARAMETER_INVALID_DATE, $parameter->getName(), $value),
			);
		}

		return $date;
	}

	/**
	 * Convert value to BackedEnum.
	 *
	 * Supports both:
	 * - Value matching: "testik" -> Status::Test (where case Test = "testik")
	 * - Name matching: "Test" -> Status::Test (fallback)
	 *
	 * @param class-string<BackedEnum> $type
	 * @throws ClientErrorException When no matching case found
	 */
	protected function toBackedEnum(mixed $value, string $type, EndpointParameter $parameter): BackedEnum
	{
		// 1. Try by value first (primary matching) - cast according to the enum's backing type:
		//    int-backed accepts only integer strings ("1", not "1.5"/"1e0"), string-backed keeps "1" as a string
		$intBacked = (string) (new ReflectionEnum($type))->getBackingType() === 'int';
		$result = match (true) {
			$intBacked && is_int($value) => $type::tryFrom($value),
			$intBacked && is_string($value) && preg_match('/^-?\d+\z/', $value) === 1 => $type::tryFrom((int) $value),
			$intBacked => null,
			default => $type::tryFrom((string) $value),
		};

		if ($result !== null) {
			return $result;
		}

		// 2. Try by case name (fallback for better DX)
		foreach ($type::cases() as $case) {
			if ($case->name === $value) {
				return $case;
			}
		}

		// 3. Fail with helpful message showing both name and value
		$validOptions = array_map(
			static fn (BackedEnum $c): string => $c->name . ' (' . $c->value . ')',
			$type::cases(),
		);

		throw new ClientErrorException(sprintf(
			ErrorMessages::PARAMETER_INVALID_ENUM,
			$parameter->getName(),
			$value,
			implode(', ', $validOptions),
		));
	}

	/**
	 * @param class-string<UnitEnum> $type
	 * @throws ClientErrorException
	 */
	protected function toUnitEnum(mixed $value, string $type, EndpointParameter $parameter): UnitEnum
	{
		foreach ($type::cases() as $case) {
			if ($case->name === $value) {
				return $case;
			}
		}

		throw new ClientErrorException(sprintf(
			ErrorMessages::PARAMETER_INVALID_ENUM,
			$parameter->getName(),
			$value,
			implode(', ', array_map(static fn ($c) => $c->name, $type::cases())),
		));
	}

}
