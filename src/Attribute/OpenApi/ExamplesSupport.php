<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

/**
 * Trait for building OpenAPI examples map from Examples objects.
 *
 * Provides utility for converting array of Examples objects
 * to OpenAPI-compliant examples map.
 *
 * Used by: JsonContent, MediaType
 */
trait ExamplesSupport
{

	/**
	 * Build OpenAPI examples map from Examples objects.
	 *
	 * @param array<Examples>|null $examples
	 * @return array<string, mixed>
	 */
	protected function buildExamplesMap(array|null $examples): array
	{
		if ($examples === null || $examples === []) {
			return [];
		}

		$examplesMap = [];

		foreach ($examples as $exampleObj) {
			$name = $exampleObj->getName() ?? 'default';
			$examplesMap[$name] = $exampleObj->toOpenApiSpec();
		}

		return $examplesMap;
	}

}
