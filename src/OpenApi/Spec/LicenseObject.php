<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class LicenseObject implements JsonSerializable
{

	public function __construct(
		public string $name,
		public string|null $url = null,
		public string|null $identifier = null,
	)
	{
	}

	/**
	 * @return array<string, string>
	 */
	public function jsonSerialize(): array
	{
		$data = ['name' => $this->name];

		if ($this->url !== null) {
			$data['url'] = $this->url;
		}

		if ($this->identifier !== null) {
			$data['identifier'] = $this->identifier;
		}

		return $data;
	}

}
