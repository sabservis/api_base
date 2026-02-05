<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ExternalDocumentation implements OpenApiAttributeInterface
{

	public function __construct(
		public string|null $url = null,
		public string|null $description = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->url !== null) {
			$spec['url'] = $this->url;
		}

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		return $spec;
	}

}
