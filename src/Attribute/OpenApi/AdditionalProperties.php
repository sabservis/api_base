<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * OpenAPI Additional Properties attribute.
 *
 * Used to define the schema for additional properties in an object.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class AdditionalProperties implements OpenApiAttributeInterface
{

	public function __construct(
		public string|null $type = null,
		public string|null $format = null,
		public string|null $description = null,
		public Items|null $items = null,
		public string|null $ref = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->ref !== null) {
			$spec['$ref'] = '#/components/schemas/' . $this->ref;

			return $spec;
		}

		if ($this->type !== null) {
			$spec['type'] = $this->type;
		}

		if ($this->format !== null) {
			$spec['format'] = $this->format;
		}

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->items !== null) {
			$spec['items'] = $this->items->toOpenApiSpec();
		}

		return $spec;
	}

}
