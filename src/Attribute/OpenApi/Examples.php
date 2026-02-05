<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Examples implements OpenApiAttributeInterface
{

	public function __construct(
		public string|null $example = null,
		public string|null $summary = null,
		public string|null $description = null,
		public mixed $value = null,
		public string|null $externalValue = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->summary !== null) {
			$spec['summary'] = $this->summary;
		}

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->value !== null) {
			$spec['value'] = $this->value;
		}

		if ($this->externalValue !== null) {
			$spec['externalValue'] = $this->externalValue;
		}

		return $spec;
	}

	public function getName(): string|null
	{
		return $this->example;
	}

}
