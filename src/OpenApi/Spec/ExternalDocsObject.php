<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ExternalDocsObject implements JsonSerializable
{

	public function __construct(
		public string $url,
		public string|null $description = null,
	)
	{
	}

	/**
	 * @return array<string, string>
	 */
	public function jsonSerialize(): array
	{
		$data = ['url' => $this->url];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		return $data;
	}

}
