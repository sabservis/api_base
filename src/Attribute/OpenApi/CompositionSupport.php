<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use function is_array;
use function is_string;

/**
 * Trait for building OpenAPI composition arrays (oneOf, anyOf, allOf).
 *
 * Used by Items, JsonContent, and Schema attributes.
 */
trait CompositionSupport
{

	use ClassNameSupport;

	/**
	 * Build OpenAPI composition array from mixed items (Schema, class-string, or raw array).
	 *
	 * @param array<Schema|class-string|array<string, mixed>> $items
	 * @return array<array<string, mixed>>
	 */
	protected function buildCompositionArray(array $items): array
	{
		$result = [];

		foreach ($items as $item) {
			if ($item instanceof Schema) {
				$result[] = $item->toOpenApiSpec();
			} elseif (is_string($item) && $this->looksLikeClassName($item)) {
				$result[] = ['$ref' => '#/components/schemas/' . $this->getShortClassName($item)];
			} elseif (is_array($item)) {
				$result[] = $item;
			}
		}

		return $result;
	}

}
