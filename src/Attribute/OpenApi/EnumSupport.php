<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use BackedEnum;
use UnitEnum;
use function array_map;
use function enum_exists;
use function is_array;
use function is_string;

/**
 * Trait for resolving PHP enums to OpenAPI enum values.
 *
 * Handles both backed enums (returning values) and unit enums (returning names).
 * Supports direct array values or PHP enum class references.
 *
 * Used by: Items, Property
 */
trait EnumSupport
{

	/** @var array<mixed>|null */
	private array|null $resolvedEnum = null;

	/**
	 * Resolve enum property to array of values for OpenAPI spec.
	 *
	 * Supports:
	 * - Direct array of values: ['active', 'inactive']
	 * - PHP BackedEnum class: Status::class (returns values)
	 * - PHP UnitEnum class: Status::class (returns names)
	 *
	 * @param class-string<UnitEnum>|array<mixed>|string|null $enum
	 * @return array<mixed>
	 */
	protected function doResolveEnum(string|array|null $enum): array
	{
		if ($this->resolvedEnum !== null) {
			return $this->resolvedEnum;
		}

		if ($enum === null) {
			return $this->resolvedEnum = [];
		}

		// Already an array of values
		if (is_array($enum)) {
			return $this->resolvedEnum = $enum;
		}

		// Enum class name - resolve to cases
		if (is_string($enum) && enum_exists($enum)) {
			/** @var class-string<UnitEnum> $enumClass */
			$enumClass = $enum;
			$cases = $enumClass::cases();

			return $this->resolvedEnum = array_map(
				static fn (UnitEnum $case) => $case instanceof BackedEnum ? $case->value : $case->name,
				$cases,
			);
		}

		return $this->resolvedEnum = [];
	}

}
