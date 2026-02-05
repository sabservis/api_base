<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class MediaTypeObject implements JsonSerializable
{

	/**
	 * @param array<string, mixed>|null $examples
	 * @param array<string, mixed>|null $encoding
	 */
	public function __construct(
		public SchemaObject|null $schema = null,
		public mixed $example = null,
		public array|null $examples = null,
		public array|null $encoding = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->schema !== null) {
			$data['schema'] = $this->schema;
		}

		if ($this->example !== null) {
			$data['example'] = $this->example;
		}

		if ($this->examples !== null) {
			$data['examples'] = $this->examples;
		}

		if ($this->encoding !== null) {
			$data['encoding'] = $this->encoding;
		}

		return $data;
	}

}
