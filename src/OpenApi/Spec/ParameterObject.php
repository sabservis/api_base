<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ParameterObject implements JsonSerializable
{

	public const InPath = 'path';

	public const InQuery = 'query';

	public const InHeader = 'header';

	public const InCookie = 'cookie';

	/**
	 * @param array<string, mixed>|null $examples
	 */
	public function __construct(
		public string $name,
		public string $in,
		public string|null $description = null,
		public bool|null $required = null,
		public bool|null $deprecated = null,
		public bool|null $allowEmptyValue = null,
		public string|null $style = null,
		public bool|null $explode = null,
		public SchemaObject|null $schema = null,
		public mixed $example = null,
		public array|null $examples = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [
			'name' => $this->name,
			'in' => $this->in,
		];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->required !== null) {
			$data['required'] = $this->required;
		}

		if ($this->deprecated !== null) {
			$data['deprecated'] = $this->deprecated;
		}

		if ($this->allowEmptyValue !== null) {
			$data['allowEmptyValue'] = $this->allowEmptyValue;
		}

		if ($this->style !== null) {
			$data['style'] = $this->style;
		}

		if ($this->explode !== null) {
			$data['explode'] = $this->explode;
		}

		if ($this->schema !== null) {
			$data['schema'] = $this->schema;
		}

		if ($this->example !== null) {
			$data['example'] = $this->example;
		}

		if ($this->examples !== null) {
			$data['examples'] = $this->examples;
		}

		return $data;
	}

}
