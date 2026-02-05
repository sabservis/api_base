<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use function class_exists;
use function end;
use function explode;
use function is_array;
use function is_string;
use function str_contains;

/**
 * Trait for building OpenAPI composition arrays (oneOf, anyOf, allOf).
 *
 * Used by Items, JsonContent, and Schema attributes.
 */
trait CompositionSupport
{

	/**
	 * Build OpenAPI composition array from mixed items (Schema, class-string, or raw array).
	 *
	 * @param array<Schema|class-string|array<string, mixed>> $items
	 * @return array<array<string, mixed>>
	 */
	private function buildCompositionArray(array $items): array
	{
		$result = [];

		foreach ($items as $item) {
			if ($item instanceof Schema) {
				$result[] = $item->toOpenApiSpec();
			} elseif (is_string($item) && class_exists($item)) {
				$result[] = ['$ref' => '#/components/schemas/' . $this->getShortClassName($item)];
			} elseif (is_array($item)) {
				$result[] = $item;
			}
		}

		return $result;
	}

	/**
	 * Extract short class name from fully-qualified class name.
	 */
	private function getShortClassName(string $className): string
	{
		$parts = explode('\\', $className);

		return end($parts);
	}

	/**
	 * Check if a string looks like a fully-qualified class name.
	 * A class name should contain a namespace separator (backslash).
	 */
	private function looksLikeClassName(string $value): bool
	{
		return str_contains($value, '\\');
	}

}
