<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Schema implements OpenApiAttributeInterface
{

	use CompositionSupport;

	/**
	 * @param class-string|string|null $ref
	 * @param string|array<string>|null $type
	 * @param array<string>|null $required
	 * @param array<mixed>|null $enum
	 * @param array<mixed>|null $oneOf
	 * @param array<mixed>|null $anyOf
	 * @param array<mixed>|null $allOf
	 * @param array<string, mixed>|null $x
	 */
	public function __construct(
		public string|null $ref = null,
		public string|null $schema = null,
		public string|null $title = null,
		public string|null $description = null,
		public string|array|null $type = null,
		public string|null $format = null,
		public array|null $required = null,
		public array|null $enum = null,
		public int|float|null $minimum = null,
		public int|float|null $maximum = null,
		public int|null $minLength = null,
		public int|null $maxLength = null,
		public int|null $minItems = null,
		public int|null $maxItems = null,
		public string|null $pattern = null,
		public mixed $default = null,
		public mixed $example = null,
		public bool|null $nullable = null,
		public bool|null $deprecated = null,
		public bool|null $readOnly = null,
		public bool|null $writeOnly = null,
		public Items|null $items = null,
		public array|null $oneOf = null,
		public array|null $anyOf = null,
		public array|null $allOf = null,
		public array|null $x = null,
		public bool $hidden = false,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		if ($this->ref !== null) {
			return ['$ref' => '#/components/schemas/' . $this->getRefName()];
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

		if ($this->required !== null && $this->required !== []) {
			$spec['required'] = $this->required;
		}

		if ($this->enum !== null) {
			$spec['enum'] = $this->enum;
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

		if ($this->minItems !== null) {
			$spec['minItems'] = $this->minItems;
		}

		if ($this->maxItems !== null) {
			$spec['maxItems'] = $this->maxItems;
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

		if ($this->oneOf !== null) {
			$spec['oneOf'] = $this->buildCompositionArray($this->oneOf);
		}

		if ($this->anyOf !== null) {
			$spec['anyOf'] = $this->buildCompositionArray($this->anyOf);
		}

		if ($this->allOf !== null) {
			$spec['allOf'] = $this->buildCompositionArray($this->allOf);
		}

		if ($this->x !== null) {
			foreach ($this->x as $key => $value) {
				$spec['x-' . $key] = $value;
			}
		}

		return $spec;
	}

	private function getRefName(): string
	{
		if ($this->ref !== null && $this->looksLikeClassName($this->ref)) {
			return $this->getShortClassName($this->ref);
		}

		return $this->ref ?? '';
	}

}
