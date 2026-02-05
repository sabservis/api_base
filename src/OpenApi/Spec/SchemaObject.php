<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class SchemaObject implements JsonSerializable
{

	/**
	 * @param string|array<string>|null $type
	 * @param array<string, SchemaObject>|null $properties
	 * @param array<string>|null $required
	 * @param array<mixed>|null $enum
	 * @param array<SchemaObject>|null $allOf
	 * @param array<SchemaObject>|null $oneOf
	 * @param array<SchemaObject>|null $anyOf
	 */
	public function __construct(
		public string|null $ref = null,
		public string|array|null $type = null,
		public string|null $format = null,
		public string|null $title = null,
		public string|null $description = null,
		public array|null $properties = null,
		public array|null $required = null,
		public array|null $enum = null,
		public int|float|null $minimum = null,
		public int|float|null $maximum = null,
		public int|float|null $exclusiveMinimum = null,
		public int|float|null $exclusiveMaximum = null,
		public int|null $minLength = null,
		public int|null $maxLength = null,
		public int|null $minItems = null,
		public int|null $maxItems = null,
		public bool|null $uniqueItems = null,
		public string|null $pattern = null,
		public mixed $default = null,
		public mixed $example = null,
		public bool|null $nullable = null,
		public bool|null $deprecated = null,
		public bool|null $readOnly = null,
		public bool|null $writeOnly = null,
		public self|null $items = null,
		public bool|self|null $additionalProperties = null,
		public array|null $allOf = null,
		public array|null $oneOf = null,
		public array|null $anyOf = null,
		public DiscriminatorObject|null $discriminator = null,
		public self|null $not = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		// Reference handling
		if ($this->ref !== null) {
			$refSchema = ['$ref' => '#/components/schemas/' . $this->ref];

			// OpenAPI 3.0: nullable ref requires oneOf wrapper
			if ($this->nullable === true) {
				$data = [
					'oneOf' => [$refSchema],
					'nullable' => true,
				];

				if ($this->description !== null) {
					$data['description'] = $this->description;
				}

				if ($this->readOnly !== null) {
					$data['readOnly'] = $this->readOnly;
				}

				if ($this->writeOnly !== null) {
					$data['writeOnly'] = $this->writeOnly;
				}

				if ($this->deprecated !== null) {
					$data['deprecated'] = $this->deprecated;
				}

				if ($this->title !== null) {
					$data['title'] = $this->title;
				}

				if ($this->example !== null) {
					$data['example'] = $this->example;
				}

				if ($this->default !== null) {
					$data['default'] = $this->default;
				}

				return $data;
			}

			// Non-nullable ref with extra metadata (description, etc.)
			if ($this->description !== null || $this->readOnly !== null || $this->writeOnly !== null
				|| $this->deprecated !== null || $this->title !== null || $this->example !== null
				|| $this->default !== null
			) {
				$data = $refSchema;

				if ($this->description !== null) {
					$data['description'] = $this->description;
				}

				if ($this->readOnly !== null) {
					$data['readOnly'] = $this->readOnly;
				}

				if ($this->writeOnly !== null) {
					$data['writeOnly'] = $this->writeOnly;
				}

				if ($this->deprecated !== null) {
					$data['deprecated'] = $this->deprecated;
				}

				if ($this->title !== null) {
					$data['title'] = $this->title;
				}

				if ($this->example !== null) {
					$data['example'] = $this->example;
				}

				if ($this->default !== null) {
					$data['default'] = $this->default;
				}

				return $data;
			}

			return $refSchema;
		}

		$data = [];

		if ($this->type !== null) {
			$data['type'] = $this->type;
		}

		if ($this->format !== null) {
			$data['format'] = $this->format;
		}

		if ($this->title !== null) {
			$data['title'] = $this->title;
		}

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->properties !== null) {
			$data['properties'] = $this->properties;
		}

		if ($this->required !== null && $this->required !== []) {
			$data['required'] = $this->required;
		}

		if ($this->enum !== null) {
			$data['enum'] = $this->enum;
		}

		if ($this->minimum !== null) {
			$data['minimum'] = $this->minimum;
		}

		if ($this->maximum !== null) {
			$data['maximum'] = $this->maximum;
		}

		if ($this->exclusiveMinimum !== null) {
			$data['exclusiveMinimum'] = $this->exclusiveMinimum;
		}

		if ($this->exclusiveMaximum !== null) {
			$data['exclusiveMaximum'] = $this->exclusiveMaximum;
		}

		if ($this->minLength !== null) {
			$data['minLength'] = $this->minLength;
		}

		if ($this->maxLength !== null) {
			$data['maxLength'] = $this->maxLength;
		}

		if ($this->minItems !== null) {
			$data['minItems'] = $this->minItems;
		}

		if ($this->maxItems !== null) {
			$data['maxItems'] = $this->maxItems;
		}

		if ($this->uniqueItems !== null) {
			$data['uniqueItems'] = $this->uniqueItems;
		}

		if ($this->pattern !== null) {
			$data['pattern'] = $this->pattern;
		}

		if ($this->default !== null) {
			$data['default'] = $this->default;
		}

		if ($this->example !== null) {
			$data['example'] = $this->example;
		}

		if ($this->nullable !== null) {
			$data['nullable'] = $this->nullable;
		}

		if ($this->deprecated !== null) {
			$data['deprecated'] = $this->deprecated;
		}

		if ($this->readOnly !== null) {
			$data['readOnly'] = $this->readOnly;
		}

		if ($this->writeOnly !== null) {
			$data['writeOnly'] = $this->writeOnly;
		}

		if ($this->items !== null) {
			$data['items'] = $this->items;
		}

		if ($this->additionalProperties !== null) {
			$data['additionalProperties'] = $this->additionalProperties;
		}

		if ($this->allOf !== null) {
			$data['allOf'] = $this->allOf;
		}

		if ($this->oneOf !== null) {
			$data['oneOf'] = $this->oneOf;
		}

		if ($this->anyOf !== null) {
			$data['anyOf'] = $this->anyOf;
		}

		if ($this->discriminator !== null) {
			$data['discriminator'] = $this->discriminator;
		}

		if ($this->not !== null) {
			$data['not'] = $this->not;
		}

		return $data;
	}

}
