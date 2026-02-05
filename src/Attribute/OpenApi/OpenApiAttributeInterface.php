<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

interface OpenApiAttributeInterface
{

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array;

}
