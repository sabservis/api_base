<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Generator;

use function array_column;
use function array_count_values;
use function array_fill_keys;
use function array_slice;
use function array_unique;
use function count;
use function explode;
use function implode;
use function in_array;
use function lcfirst;
use function max;
use function strtolower;
use function ucfirst;
use function usort;

/**
 * Makes generated operationIds unique (OpenAPI requires it).
 *
 * Explicit operationIds are never changed. Generated ids that collide get:
 * - namespace prefix when controllers differ (Admin\UserController::list → adminUserList)
 * - HTTP method suffix when one method serves several HTTP methods (pingCheckGet, pingCheckPost)
 * - numeric suffix for aliases of one endpoint (same class and HTTP method) and as last resort
 */
final class OperationIdResolver
{

	/**
	 * @param list<array{class: string, httpMethod: string, id: string, explicit: bool, path?: string}> $operations
	 * @return list<string>
	 */
	public static function resolve(array $operations): array
	{
		$ids = array_column($operations, 'id');
		$explicit = [];
		$groups = [];

		foreach ($operations as $index => $operation) {
			if ($operation['explicit']) {
				$explicit[$operation['id']] = true;
			} else {
				$groups[$operation['id']][] = $index;
			}
		}

		$taken = $explicit;

		// Non-colliding generated ids stay as they are
		foreach ($groups as $id => $indexes) {
			if (count($indexes) === 1 && !isset($explicit[$id])) {
				$taken[$id] = true;
			}
		}

		foreach ($groups as $id => $indexes) {
			if (count($indexes) === 1 && !isset($explicit[$id])) {
				continue;
			}

			foreach (self::disambiguate($operations, $indexes, (string) $id, $taken) as $index => $unique) {
				$ids[$index] = $unique;
				$taken[$unique] = true;
			}
		}

		return $ids;
	}

	/**
	 * @param list<array{class: string, httpMethod: string, id: string, explicit: bool, path?: string}> $operations
	 * @param list<int> $indexes
	 * @param array<string, bool> $taken
	 * @return array<int, string>
	 */
	private static function disambiguate(array $operations, array $indexes, string $id, array $taken): array
	{
		// Aliases (same class and HTTP method) can't be told apart - resolve one of them, number the rest
		$aliases = [];

		foreach ($indexes as $index) {
			$aliases[$operations[$index]['class'] . ' ' . $operations[$index]['httpMethod']][] = $index;
		}

		$resolved = self::disambiguateDistinct($operations, array_column($aliases, 0), $id, $taken);
		$assigned = $taken + array_fill_keys($resolved, true);

		foreach ($aliases as $group) {
			$base = $resolved[$group[0]];
			// Numbered by path, not by attribute order - reordering aliases must not swap their ids
			$rest = array_slice($group, 1);
			usort(
				$rest,
				static fn (int $a, int $b): int => ($operations[$a]['path'] ?? '') <=> ($operations[$b]['path'] ?? ''),
			);

			foreach ($rest as $index) {
				$n = 2;

				while (isset($assigned[$base . $n])) {
					$n++;
				}

				$resolved[$index] = $base . $n;
				$assigned[$base . $n] = true;
			}
		}

		return $resolved;
	}

	/**
	 * @param list<array{class: string, httpMethod: string, id: string, explicit: bool, path?: string}> $operations
	 * @param list<int> $indexes
	 * @param array<string, bool> $taken
	 * @return array<int, string>
	 */
	private static function disambiguateDistinct(array $operations, array $indexes, string $id, array $taken): array
	{
		$classes = [];
		$maxDepth = 0;

		foreach ($indexes as $index) {
			$classes[] = $operations[$index]['class'];
			$maxDepth = max($maxDepth, count(explode('\\', $operations[$index]['class'])) - 1);
		}

		// Different controllers → prefer namespace prefix over HTTP method suffix
		$startDepth = count(array_unique($classes)) > 1 ? 1 : 0;

		for ($depth = $startDepth; $depth <= $maxDepth; $depth++) {
			foreach ([false, true] as $withMethod) {
				$candidates = [];

				foreach ($indexes as $index) {
					$candidates[$index] = self::candidate($operations[$index], $id, $depth, $withMethod);
				}

				if (self::isUsable($candidates, $taken)) {
					return $candidates;
				}
			}
		}

		$candidates = [];

		foreach ($indexes as $index) {
			$base = self::candidate($operations[$index], $id, $maxDepth, true);
			$candidate = $base;

			for ($n = 2; isset($taken[$candidate]) || in_array($candidate, $candidates, true); $n++) {
				$candidate = $base . $n;
			}

			$candidates[$index] = $candidate;
		}

		return $candidates;
	}

	/**
	 * @param array{class: string, httpMethod: string, id: string, explicit: bool, path?: string} $operation
	 */
	private static function candidate(array $operation, string $id, int $depth, bool $withMethod): string
	{
		$namespace = array_slice(explode('\\', $operation['class']), 0, -1);
		$prefix = $depth > 0 ? implode('', array_slice($namespace, -$depth)) : '';
		$suffix = $withMethod ? ucfirst(strtolower($operation['httpMethod'])) : '';

		return lcfirst($prefix . ucfirst($id)) . $suffix;
	}

	/**
	 * @param array<int, string> $candidates
	 * @param array<string, bool> $taken
	 */
	private static function isUsable(array $candidates, array $taken): bool
	{
		if (count(array_count_values($candidates)) !== count($candidates)) {
			return false;
		}

		foreach ($candidates as $candidate) {
			if (isset($taken[$candidate])) {
				return false;
			}
		}

		return true;
	}

}
