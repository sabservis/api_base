<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use UnitEnum;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Property implements OpenApiAttributeInterface
{

	use EnumSupport;
	use ReferencedClassesSupport;

	/**
	 * @param string|array<string>|null $type
	 * @param class-string<UnitEnum>|array<mixed>|null $enum
	 */
	public function __construct(
		public string|null $property = null,
		public string|null $title = null,
		public string|null $description = null,
		public string|array|null $type = null,
		public string|null $format = null,
		public string|array|null $enum = null,
		public int|float|null $minimum = null,
		public int|float|null $maximum = null,
		public int|null $minLength = null,
		public int|null $maxLength = null,
		public string|null $pattern = null,
		public mixed $default = null,
		public mixed $example = null,
		public bool|null $nullable = null,
		public bool|null $deprecated = null,
		public bool|null $readOnly = null,
		public bool|null $writeOnly = null,
		public bool|null $required = null,
		public Items|null $items = null,
		public string|null $ref = null,
		public AdditionalProperties|null $additionalProperties = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		if ($this->ref !== null) {
			return ['$ref' => '#/components/schemas/' . $this->resolveRefName($this->ref)];
		}

		$spec = [];

		if ($this->type !== null) {
			$spec['type'] = $this->type;
		}

		if ($this->format !== null) {
			$spec['format'] = $this->format;
		}

		if ($this->title !== null) {
			$spec['title'] = $this->title;
		}

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->enum !== null) {
			$spec['enum'] = $this->resolveEnum();
		}

		if ($this->minimum !== null) {
			$spec['minimum'] = $this->minimum;
		}

		if ($this->maximum !== null) {
			$spec['maximum'] = $this->maximum;
		}

		if ($this->minLength !== null) {
			$spec['minLength'] = $this->minLength;
		}

		if ($this->maxLength !== null) {
			$spec['maxLength'] = $this->maxLength;
		}

		if ($this->pattern !== null) {
			$spec['pattern'] = $this->pattern;
		}

		if ($this->default !== null) {
			$spec['default'] = $this->default;
		}

		if ($this->example !== null) {
			$spec['example'] = $this->example;
		}

		if ($this->nullable !== null) {
			$spec['nullable'] = $this->nullable;
		}

		if ($this->deprecated !== null) {
			$spec['deprecated'] = $this->deprecated;
		}

		if ($this->readOnly !== null) {
			$spec['readOnly'] = $this->readOnly;
		}

		if ($this->writeOnly !== null) {
			$spec['writeOnly'] = $this->writeOnly;
		}

		if ($this->items !== null) {
			$spec['items'] = $this->items->toOpenApiSpec();
		}

		if ($this->additionalProperties !== null) {
			$spec['additionalProperties'] = $this->additionalProperties->toOpenApiSpec();
		}

		return $spec;
	}

	/**
	 * @return array<mixed>
	 */
	public function resolveEnum(): array
	{
		return $this->doResolveEnum($this->enum);
	}

	/**
	 * Get all class references from ref and items for schema registration.
	 *
	 * @return array<class-string>
	 */
	public function getReferencedClasses(): array
	{
		return [
			...$this->extractRefClasses($this->ref),
			...$this->extractItemsClasses($this->items),
		];
	}

}
