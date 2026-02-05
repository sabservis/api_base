<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

/**
 * Define a server variable for URL templating.
 *
 * Used within Server attribute to define dynamic parts of the URL.
 *
 * Usage:
 *   #[Server(
 *       url: 'https://{environment}.api.example.com/v{version}',
 *       description: 'API server',
 *       variables: [
 *           new ServerVariable(name: 'environment', default: 'prod', enum: ['prod', 'staging', 'dev']),
 *           new ServerVariable(name: 'version', default: '1', description: 'API version'),
 *       ]
 *   )]
 */
final class ServerVariable implements OpenApiAttributeInterface
{

	/**
	 * @param string $name Variable name (matches {name} in URL)
	 * @param string $default Default value (required by OpenAPI spec)
	 * @param string|null $description Description of the variable
	 * @param array<string> $enum Allowed values for the variable
	 */
	public function __construct(
		public string $name,
		public string $default,
		public string|null $description = null,
		public array $enum = [],
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = ['default' => $this->default];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->enum !== []) {
			$spec['enum'] = $this->enum;
		}

		return $spec;
	}

	public function getName(): string
	{
		return $this->name;
	}

}
