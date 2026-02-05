<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Info implements OpenApiAttributeInterface
{

	public function __construct(
		public string $title,
		public string $version,
		public string|null $description = null,
		public string|null $termsOfService = null,
		public Contact|null $contact = null,
		public License|null $license = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [
			'title' => $this->title,
			'version' => $this->version,
		];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->termsOfService !== null) {
			$spec['termsOfService'] = $this->termsOfService;
		}

		if ($this->contact !== null) {
			$spec['contact'] = $this->contact->toOpenApiSpec();
		}

		if ($this->license !== null) {
			$spec['license'] = $this->license->toOpenApiSpec();
		}

		return $spec;
	}

}
