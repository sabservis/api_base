<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use function array_merge;
use function class_exists;
use function is_string;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class JsonContent implements OpenApiAttributeInterface
{

	use CompositionSupport;

	/**
	 * @param class-string|string|null $ref
	 * @param array<Property>|null $properties
	 * @param array<mixed>|null $enum
	 * @param array<mixed>|null $oneOf
	 * @param array<mixed>|null $anyOf
	 * @param array<mixed>|null $allOf
	 */
	public function __construct(
		public string|null $ref = null,
		public string|null $type = null,
		public string|null $format = null,
		public string|null $description = null,
		public array|null $properties = null,
		public array|null $enum = null,
		public Items|null $items = null,
		public mixed $example = null,
		public array|null $oneOf = null,
		public array|null $anyOf = null,
		public array|null $allOf = null,
		public AdditionalProperties|bool|null $additionalProperties = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$schema = [];

		if ($this->ref !== null) {
			$refName = class_exists($this->ref)
				? $this->getShortClassName($this->ref)
				: $this->ref;

			$schema['$ref'] = '#/components/schemas/' . $refName;
		} else {
			if ($this->type !== null) {
				$schema['type'] = $this->type;
			}

			if ($this->format !== null) {
				$schema['format'] = $this->format;
			}

			if ($this->description !== null) {
				$schema['description'] = $this->description;
			}

			if ($this->enum !== null) {
				$schema['enum'] = $this->enum;
			}

			if ($this->items !== null) {
				$schema['items'] = $this->items->toOpenApiSpec();
			}

			if ($this->properties !== null) {
				$props = [];

				foreach ($this->properties as $property) {
					if ($property->property !== null) {
						$props[$property->property] = $property->toOpenApiSpec();
					}
				}

				if ($props !== []) {
					$schema['properties'] = $props;
				}
			}

			if ($this->additionalProperties !== null) {
				$schema['additionalProperties'] = $this->additionalProperties instanceof AdditionalProperties
					? $this->additionalProperties->toOpenApiSpec()
					: $this->additionalProperties;
			}

			if ($this->example !== null) {
				$schema['example'] = $this->example;
			}

			if ($this->oneOf !== null) {
				$schema['oneOf'] = $this->buildCompositionArray($this->oneOf);
			}

			if ($this->anyOf !== null) {
				$schema['anyOf'] = $this->buildCompositionArray($this->anyOf);
			}

			if ($this->allOf !== null) {
				$schema['allOf'] = $this->buildCompositionArray($this->allOf);
			}
		}

		return ['schema' => $schema];
	}

	/**
	 * Get all class references from ref, items, oneOf/anyOf/allOf for schema registration.
	 *
	 * Note: We don't use class_exists() here because this method is called during
	 * DI compilation (beforeCompile) when classes might not be autoloaded yet.
	 * Instead, we check if the string looks like a fully-qualified class name.
	 *
	 * @return array<class-string>
	 */
	public function getReferencedClasses(): array
	{
		$classes = [];

		if ($this->ref !== null && $this->looksLikeClassName($this->ref)) {
			/** @var class-string $className */
			$className = $this->ref;
			$classes[] = $className;
		}

		if ($this->items !== null) {
			$classes = array_merge($classes, $this->items->getReferencedClasses());
		}

		foreach ([$this->oneOf, $this->anyOf, $this->allOf] as $composition) {
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

}
