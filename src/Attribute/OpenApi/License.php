<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class License implements OpenApiAttributeInterface
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
	public function toOpenApiSpec(): array
	{
		$spec = ['name' => $this->name];

		if ($this->url !== null) {
			$spec['url'] = $this->url;
		}

		if ($this->identifier !== null) {
			$spec['identifier'] = $this->identifier;
		}

		return $spec;
	}

}
