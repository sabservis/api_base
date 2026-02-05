<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use function array_merge;

/**
 * Trait for building content arrays from JsonContent and MediaType objects.
 *
 * Provides shared logic for building OpenAPI content specifications from
 * arrays of JsonContent and MediaType objects.
 *
 * Used by: RequestBody, Response
 */
trait ContentBuilderSupport
{

	/**
	 * Build content specification from an array of JsonContent/MediaType objects.
	 *
	 * @param array<JsonContent|MediaType>|null $contentArray
	 * @return array<string, mixed>
	 */
	protected function buildContentSpec(array|null $contentArray): array
	{
		if ($contentArray === null) {
			return [];
		}

		$contentSpec = [];

		foreach ($contentArray as $mediaType) {
			if ($mediaType instanceof JsonContent) {
				$contentSpec['application/json'] = $mediaType->toOpenApiSpec();
			} elseif ($mediaType instanceof MediaType) {
				$contentSpec = array_merge($contentSpec, $mediaType->toOpenApiSpec());
			}
		}

		return $contentSpec;
	}

	/**
	 * Build JSON content specification from a class reference.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function buildJsonContentFromRef(string $refName): array
	{
		return [
			'application/json' => [
				'schema' => ['$ref' => '#/components/schemas/' . $refName],
			],
		];
	}

}
