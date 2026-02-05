<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ContactObject implements JsonSerializable
{

	public function __construct(
		public string|null $name = null,
		public string|null $url = null,
		public string|null $email = null,
	)
	{
	}

	/**
	 * @return array<string, string>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->name !== null) {
			$data['name'] = $this->name;
		}

		if ($this->url !== null) {
			$data['url'] = $this->url;
		}

		if ($this->email !== null) {
			$data['email'] = $this->email;
		}

		return $data;
	}

}
