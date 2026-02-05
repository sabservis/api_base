<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use function array_merge;
use function class_exists;
use function end;
use function explode;
use function is_array;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequestBody implements OpenApiAttributeInterface
{

	/** @var array<JsonContent|MediaType>|null */
	public array|null $contentArray = null;

	/**
	 * @param class-string|string|null $ref Reference to a DTO class
	 * @param JsonContent|MediaType|array<JsonContent|MediaType>|null $content
	 */
	public function __construct(
		public string|null $ref = null,
		public string|null $description = null,
		public bool|null $required = null,
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

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->required !== null) {
			$spec['required'] = $this->required;
		}

		// If we have a direct class reference, create JSON content
		if ($this->ref !== null) {
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
					// MediaType should specify its own media type
					$contentSpec = array_merge($contentSpec, $mediaType->toOpenApiSpec());
				}
			}

			if ($contentSpec !== []) {
				$spec['content'] = $contentSpec;
			}
		}

		return $spec;
	}

	private function getShortClassName(string $className): string
	{
		$parts = explode('\\', $className);

		return end($parts);
	}

}
