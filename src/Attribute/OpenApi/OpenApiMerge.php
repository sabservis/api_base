<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Merge custom OpenAPI specification into the generated operation.
 *
 * Values from this attribute take precedence over auto-generated values.
 * Deep merge is used for nested arrays.
 *
 * Usage:
 *   #[OpenApiMerge([
 *       'deprecated' => true,
 *       'externalDocs' => ['url' => 'https://docs.example.com'],
 *       'x-custom' => 'value',
 *       'responses' => [
 *           '418' => ['description' => "I'm a teapot"],
 *       ],
 *   ])]
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class OpenApiMerge implements OpenApiAttributeInterface
{

	/**
	 * @param array<string, mixed> $spec OpenAPI operation spec to merge
	 */
	public function __construct(public array $spec)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		return $this->spec;
	}

}
