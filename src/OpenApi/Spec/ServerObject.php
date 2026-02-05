<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ServerObject implements JsonSerializable
{

	/**
	 * @param array<string, ServerVariableObject> $variables
	 */
	public function __construct(
		public string $url,
		public string|null $description = null,
		public array $variables = [],
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['url' => $this->url];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->variables !== []) {
			$data['variables'] = $this->variables;
		}

		return $data;
	}

}
