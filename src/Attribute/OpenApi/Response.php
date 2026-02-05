<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use function array_merge;
use function class_exists;
use function end;
use function explode;
use function is_array;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Response implements OpenApiAttributeInterface
{

	/** @var array<JsonContent|MediaType>|null */
	public array|null $contentArray = null;

	/**
	 * @param class-string|string|null $ref Reference to a single DTO class
	 * @param class-string|string|null $listRef Reference to a DTO class for list response (array of items)
	 * @param bool $withMeta When true with listRef, wraps response in { data: [], meta: {} }
	 * @param JsonContent|MediaType|array<JsonContent|MediaType>|null $content
	 */
	public function __construct(
		public int|string $response = 200,
		public string|null $description = null,
		public string|null $ref = null,
		public string|null $listRef = null,
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
			$refName = class_exists($this->listRef)
				? $this->getShortClassName($this->listRef)
				: $this->listRef;

			$itemsSchema = ['$ref' => '#/components/schemas/' . $refName];

			// phpcs:ignore SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed
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
			} else {
				// Plain array: [...]
				$spec['content'] = [
					'application/json' => [
						'schema' => [
							'type' => 'array',
							'items' => $itemsSchema,
						],
					],
				];
			}
		} elseif ($this->ref !== null) {
			// Single DTO reference
			$refName = class_exists($this->ref)
				? $this->getShortClassName($this->ref)
				: $this->ref;

			$spec['content'] = [
				'application/json' => [
					'schema' => ['$ref' => '#/components/schemas/' . $refName],
				],
			];
		} elseif ($this->contentArray !== null) {
			$contentSpec = [];

			foreach ($this->contentArray as $mediaType) {
				if ($mediaType instanceof JsonContent) {
					$contentSpec['application/json'] = $mediaType->toOpenApiSpec();
				} elseif ($mediaType instanceof MediaType) {
					$contentSpec = array_merge($contentSpec, $mediaType->toOpenApiSpec());
				}
			}

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

	private function getShortClassName(string $className): string
	{
		$parts = explode('\\', $className);

		return end($parts);
	}

}
