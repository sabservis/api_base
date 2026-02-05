<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class InfoObject implements JsonSerializable
{

	public function __construct(
		public string $title,
		public string $version,
		public string|null $description = null,
		public string|null $termsOfService = null,
		public ContactObject|null $contact = null,
		public LicenseObject|null $license = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [
			'title' => $this->title,
			'version' => $this->version,
		];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->termsOfService !== null) {
			$data['termsOfService'] = $this->termsOfService;
		}

		if ($this->contact !== null) {
			$data['contact'] = $this->contact;
		}

		if ($this->license !== null) {
			$data['license'] = $this->license;
		}

		return $data;
	}

}
