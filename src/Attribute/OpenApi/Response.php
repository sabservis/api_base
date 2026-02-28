<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use function array_map;
use function is_array;
use function is_string;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Response implements OpenApiAttributeInterface
{

	use ClassNameSupport;
	use ContentBuilderSupport;

	/** @var array<JsonContent|MediaType>|null */
	public array|null $contentArray = null;

	/**
	 * @param class-string|string|null $ref Reference to a single DTO class
	 * @param class-string|string|array<class-string|string>|null $listRef Reference to DTO class(es) for list response.
	 *        Single class: array of that type. Array of classes: array with oneOf items.
	 *        Default wraps in { data: [] }. Use unwrapped: true for plain array.
	 * @param bool $unwrapped When true with listRef, produces plain array [...] instead of { data: [] }
	 * @param bool $withMeta When true with listRef, wraps response in { data: [], meta: {} }
	 * @param JsonContent|MediaType|array<JsonContent|MediaType>|null $content
	 */
	public function __construct(
		public int|string $response = 200,
		public string|null $description = null,
		public string|null $ref = null,
		public string|array|null $listRef = null,
		public bool $unwrapped = false,
		public bool $withMeta = false,
		JsonContent|MediaType|array|null $content = null,
	)
	{
		// Normalize content to array
		if ($content instanceof JsonContent || $content instanceof MediaType) {
			$this->contentArray = [$content];
		} elseif (is_array($content)) {
			$this->contentArray = $content;
		}
	}

	public function getStatusCode(): string
	{
		return (string) $this->response;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [
			'description' => $this->description ?? $this->getDefaultDescription(),
		];

		// List response with or without meta
		if ($this->listRef !== null) {
			$itemsSchema = $this->buildListItemsSchema();

			if ($this->withMeta) {
				// { data: [...], meta: { total, limit, offset } }
				$spec['content'] = [
					'application/json' => [
						'schema' => [
							'type' => 'object',
							'properties' => [
								'data' => [
									'type' => 'array',
									'items' => $itemsSchema,
								],
								'meta' => [
									'type' => 'object',
									'properties' => [
										'total' => ['type' => 'integer'],
										'limit' => ['type' => 'integer'],
										'offset' => ['type' => 'integer'],
									],
								],
							],
							'required' => ['data', 'meta'],
						],
					],
				];
			} elseif ($this->unwrapped) {
				// Plain array: [...]
				$spec['content'] = [
					'application/json' => [
						'schema' => [
							'type' => 'array',
							'items' => $itemsSchema,
						],
					],
				];
			} else {
				// Default: { data: [...] }
				$spec['content'] = [
					'application/json' => [
						'schema' => [
							'type' => 'object',
							'properties' => [
								'data' => [
									'type' => 'array',
									'items' => $itemsSchema,
								],
							],
							'required' => ['data'],
						],
					],
				];
			}
		} elseif ($this->ref !== null) {
			// Single DTO reference
			$spec['content'] = $this->buildJsonContentFromRef($this->resolveRefName($this->ref));
		} elseif ($this->contentArray !== null) {
			$contentSpec = $this->buildContentSpec($this->contentArray);

			if ($contentSpec !== []) {
				$spec['content'] = $contentSpec;
			}
		}

		return $spec;
	}

	private function getDefaultDescription(): string
	{
		return match ((int) $this->response) {
			200 => 'OK',
			201 => 'Created',
			204 => 'No Content',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			422 => 'Unprocessable Entity',
			500 => 'Internal Server Error',
			default => 'Response',
		};
	}

	/**
	 * Build items schema for list response.
	 * Returns single ref or oneOf array depending on listRef type.
	 *
	 * @return array<string, mixed>
	 */
	private function buildListItemsSchema(): array
	{
		if (is_string($this->listRef)) {
			// Single type: { $ref: ... }
			$refName = $this->resolveRefName($this->listRef);

			return ['$ref' => '#/components/schemas/' . $refName];
		}

		// Multiple types: { oneOf: [...] }
		$oneOfSchemas = array_map(
			fn (string $class) => ['$ref' => '#/components/schemas/' . $this->resolveRefName($class)],
			$this->listRef ?? [],
		);

		return ['oneOf' => $oneOfSchemas];
	}

}
