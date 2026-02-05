<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ServerVariableObject implements JsonSerializable
{

	/**
	 * @param array<string> $enum
	 */
	public function __construct(
		public string $default,
		public string|null $description = null,
		public array $enum = [],
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['default' => $this->default];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->enum !== []) {
			$data['enum'] = $this->enum;
		}

		return $data;
	}

}
