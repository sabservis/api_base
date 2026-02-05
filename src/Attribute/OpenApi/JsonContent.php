<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class JsonContent implements OpenApiAttributeInterface
{

	use CompositionSupport;
	use ExamplesSupport;
	use ReferencedClassesSupport;

	/**
	 * @param class-string|string|null $ref
	 * @param array<Property>|null $properties
	 * @param array<mixed>|null $enum
	 * @param array<mixed>|null $oneOf
	 * @param array<mixed>|null $anyOf
	 * @param array<mixed>|null $allOf
	 * @param array<Examples>|null $examples Named examples map for MediaType level
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
		public array|null $examples = null,
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
			$schema['$ref'] = '#/components/schemas/' . $this->resolveRefName($this->ref);
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

		$result = ['schema' => $schema];

		$examplesMap = $this->buildExamplesMap($this->examples);

		if ($examplesMap !== []) {
			$result['examples'] = $examplesMap;
		}

		return $result;
	}

	/**
	 * Get all class references from ref, items, properties, oneOf/anyOf/allOf for schema registration.
	 *
	 * @return array<class-string>
	 */
	public function getReferencedClasses(): array
	{
		return [
			...$this->extractRefClasses($this->ref),
			...$this->extractItemsClasses($this->items),
			...$this->extractPropertiesClasses($this->properties),
			...$this->extractCompositionClasses($this->oneOf, $this->anyOf, $this->allOf),
		];
	}

}
