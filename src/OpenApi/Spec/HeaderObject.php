<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class HeaderObject implements JsonSerializable
{

	public function __construct(
		public string|null $description = null,
		public bool|null $required = null,
		public bool|null $deprecated = null,
		public SchemaObject|null $schema = null,
		public mixed $example = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->required !== null) {
			$data['required'] = $this->required;
		}

		if ($this->deprecated !== null) {
			$data['deprecated'] = $this->deprecated;
		}

		if ($this->schema !== null) {
			$data['schema'] = $this->schema;
		}

		if ($this->example !== null) {
			$data['example'] = $this->example;
		}

		return $data;
	}

}
