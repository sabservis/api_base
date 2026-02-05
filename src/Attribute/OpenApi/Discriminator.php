<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Discriminator implements OpenApiAttributeInterface
{

	/**
	 * @param array<string, string>|null $mapping Class name to schema name mapping
	 */
	public function __construct(
		public string|null $propertyName = null,
		public array|null $mapping = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->propertyName !== null) {
			$spec['propertyName'] = $this->propertyName;
		}

		if ($this->mapping !== null && $this->mapping !== []) {
			$spec['mapping'] = $this->mapping;
		}

		return $spec;
	}

}
