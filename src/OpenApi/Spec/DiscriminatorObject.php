<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

/**
 * OpenAPI Discriminator Object.
 *
 * Used with oneOf/anyOf to help determine which schema to use for polymorphic types.
 *
 * @see https://spec.openapis.org/oas/v3.0.3#discriminator-object
 */
final class DiscriminatorObject implements JsonSerializable
{

	/**
	 * @param string $propertyName The name of the property that decides which schema to use
	 * @param array<string, string>|null $mapping Maps discriminator values to schema names
	 */
	public function __construct(
		public string $propertyName,
		public array|null $mapping = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['propertyName' => $this->propertyName];

		if ($this->mapping !== null && $this->mapping !== []) {
			$data['mapping'] = $this->mapping;
		}

		return $data;
	}

}
