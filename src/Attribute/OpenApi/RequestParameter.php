<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Sabservis\Api\OpenApi\TypeMapper;

/**
 * Base class for parameter attributes (PathParameter, QueryParameter, HeaderParameter, CookieParameter).
 *
 * Use specific parameter classes instead of this base class:
 * - #[PathParameter(name: 'id', type: 'int')]
 * - #[QueryParameter(name: 'limit', type: 'int')]
 * - #[HeaderParameter(name: 'X-API-Key')]
 * - #[CookieParameter(name: 'session')]
 */
class RequestParameter implements OpenApiAttributeInterface
{

	public const InPath = 'path';

	public const InQuery = 'query';

	public const InHeader = 'header';

	public const InCookie = 'cookie';

	public function __construct(
		public string|null $name = null,
		public string $in = self::InQuery,
		public string $type = 'string',
		public string|null $description = null,
		public bool|null $required = null,
		public bool $deprecated = false,
		public bool $allowEmptyValue = false,
		public string|null $format = null,
		public mixed $example = null,
		public string|null $style = null,
		public bool|null $explode = null,
		public Schema|null $schema = null,
	)
	{
		// Path parameters are always required
		if ($this->in === self::InPath && $this->required === null) {
			$this->required = true;
		}
	}

	public function isRequired(): bool
	{
		return $this->required ?? ($this->in === self::InPath);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [
			'in' => $this->in,
		];

		if ($this->name !== null) {
			$spec['name'] = $this->name;
		}

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->required !== null) {
			$spec['required'] = $this->required;
		} elseif ($this->in === self::InPath) {
			$spec['required'] = true;
		}

		if ($this->deprecated) {
			$spec['deprecated'] = true;
		}

		if ($this->allowEmptyValue) {
			$spec['allowEmptyValue'] = true;
		}

		if ($this->style !== null) {
			$spec['style'] = $this->style;
		}

		if ($this->explode !== null) {
			$spec['explode'] = $this->explode;
		}

		// Build schema from PHP type or use provided schema
		$spec['schema'] = $this->schema?->toOpenApiSpec() ?? $this->buildSchemaFromType();

		if ($this->example !== null) {
			$spec['example'] = $this->example;
		}

		return $spec;
	}

	/**
	 * Build OpenAPI schema from PHP type.
	 *
	 * @return array<string, mixed>
	 */
	private function buildSchemaFromType(): array
	{
		$openApiType = TypeMapper::phpToOpenApi($this->type);

		$schema = ['type' => $openApiType['type']];

		// Use explicit format if provided, otherwise use derived format
		if ($this->format !== null) {
			$schema['format'] = $this->format;
		} elseif (isset($openApiType['format'])) {
			$schema['format'] = $openApiType['format'];
		}

		return $schema;
	}

}
