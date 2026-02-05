<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class TagObject implements JsonSerializable
{

	public function __construct(
		public string $name,
		public string|null $description = null,
		public ExternalDocsObject|null $externalDocs = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['name' => $this->name];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->externalDocs !== null) {
			$data['externalDocs'] = $this->externalDocs;
		}

		return $data;
	}

}
