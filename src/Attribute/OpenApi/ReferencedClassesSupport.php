<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use function array_merge;
use function is_string;

/**
 * Trait for extracting class references from OpenAPI attributes.
 *
 * Provides utilities for finding all class references in ref, items,
 * properties, and composition arrays (oneOf/anyOf/allOf).
 *
 * Used by: JsonContent, Items, Property
 */
trait ReferencedClassesSupport
{

	use ClassNameSupport;

	/**
	 * Extract class references from a ref property.
	 *
	 * @return array<class-string>
	 */
	protected function extractRefClasses(string|null $ref): array
	{
		if ($ref !== null && $this->looksLikeClassName($ref)) {
			/** @var class-string $className */
			$className = $ref;

			return [$className];
		}

		return [];
	}

	/**
	 * Extract class references from composition arrays (oneOf/anyOf/allOf).
	 *
	 * @param array<mixed>|null $oneOf
	 * @param array<mixed>|null $anyOf
	 * @param array<mixed>|null $allOf
	 * @return array<class-string>
	 */
	protected function extractCompositionClasses(
		array|null $oneOf = null,
		array|null $anyOf = null,
		array|null $allOf = null,
	): array
	{
		$classes = [];

		foreach ([$oneOf, $anyOf, $allOf] as $composition) {
			if ($composition === null) {
				continue;
			}

			foreach ($composition as $item) {
				if (is_string($item) && $this->looksLikeClassName($item)) {
					/** @var class-string $className */
					$className = $item;
					$classes[] = $className;
				}
			}
		}

		return $classes;
	}

	/**
	 * Extract class references from Items object.
	 *
	 * @return array<class-string>
	 */
	protected function extractItemsClasses(Items|null $items): array
	{
		if ($items !== null) {
			return $items->getReferencedClasses();
		}

		return [];
	}

	/**
	 * Extract class references from Property array.
	 *
	 * @param array<Property>|null $properties
	 * @return array<class-string>
	 */
	protected function extractPropertiesClasses(array|null $properties): array
	{
		$classes = [];

		if ($properties !== null) {
			foreach ($properties as $property) {
				$classes = array_merge($classes, $property->getReferencedClasses());
			}
		}

		return $classes;
	}

}
