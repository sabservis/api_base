<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class RequestBodyObject implements JsonSerializable
{

	/**
	 * @param array<string, MediaTypeObject> $content
	 */
	public function __construct(
		public array $content,
		public string|null $description = null,
		public bool|null $required = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['content' => $this->content];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->required !== null) {
			$data['required'] = $this->required;
		}

		return $data;
	}

}
