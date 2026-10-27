<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Generator;

use ReflectionClass;
use Sabservis\Api\Attribute\OpenApi\Schema;
use function array_count_values;
use function array_slice;
use function count;
use function end;
use function explode;
use function implode;
use function in_array;
use function sort;

/**
 * Assigns unique #/components/schemas names to classes.
 *
 * - #[Schema(name: '...')] wins
 * - otherwise short class name (App\Dto\UserDto -> UserDto)
 * - classes sharing a short name get namespace segments as prefix
 *   (App\Order\ItemDto -> OrderItemDto, App\Invoice\ItemDto -> InvoiceItemDto)
 */
final class SchemaNameResolver
{

	/**
	 * @param array<class-string> $classes
	 * @return array<class-string, string>
	 */
	public static function assign(array $classes): array
	{
		sort($classes);

		$names = [];
		$explicit = [];
		$groups = [];

		foreach ($classes as $class) {
			$name = self::explicitName($class);

			if ($name !== null) {
				$names[$class] = $name;
				$explicit[] = $name;
			} else {
				$groups[self::shortName($class)][] = $class;
			}
		}

		foreach ($groups as $shortName => $group) {
			if (count($group) === 1 && !in_array($shortName, $explicit, true)) {
				$names[$group[0]] = $shortName;
			}
		}

		foreach ($groups as $shortName => $group) {
			if (count($group) === 1 && !in_array($shortName, $explicit, true)) {
				continue;
			}

			$names += self::prefixed($group, $names);
		}

		return $names;
	}

	/**
	 * @param class-string $class
	 */
	public static function shortName(string $class): string
	{
		$parts = explode('\\', $class);

		return end($parts);
	}

	/**
	 * @param array<class-string> $group
	 * @param array<class-string, string> $taken
	 * @return array<class-string, string>
	 */
	private static function prefixed(array $group, array $taken): array
	{
		for ($depth = 1; ; $depth++) {
			$candidates = [];
			$exhausted = true;

			foreach ($group as $class) {
				$parts = explode('\\', $class);
				$prefix = array_slice($parts, -1 - $depth, $depth);
				$exhausted = $exhausted && count($prefix) < $depth;
				$candidates[$class] = implode('', $prefix) . end($parts);
			}

			$unique = count(array_count_values($candidates)) === count($candidates);
			$free = true;

			foreach ($candidates as $candidate) {
				$free = $free && !in_array($candidate, $taken, true);
			}

			if (($unique && $free) || $exhausted) {
				return $candidates;
			}
		}
	}

	/**
	 * @param class-string $class
	 */
	private static function explicitName(string $class): string|null
	{
		$attributes = (new ReflectionClass($class))->getAttributes(Schema::class);

		return $attributes !== [] ? $attributes[0]->newInstance()->name : null;
	}

}
