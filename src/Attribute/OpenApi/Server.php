<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Define a server for the API or specific endpoint.
 *
 * Usage:
 *   Simple server:
 *     #[Server(url: 'https://api.example.com')]
 *
 *   Server with variables:
 *     #[Server(
 *         url: 'https://{environment}.api.example.com/v{version}',
 *         description: 'API server',
 *         variables: [
 *             new ServerVariable(name: 'environment', default: 'prod', enum: ['prod', 'staging']),
 *             new ServerVariable(name: 'version', default: '1'),
 *         ]
 *     )]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Server implements OpenApiAttributeInterface
{

	/**
	 * @param string $url Server URL (can contain {variable} placeholders)
	 * @param string|null $description Server description
	 * @param array<ServerVariable> $variables Server variables for URL templating
	 */
	public function __construct(
		public string $url,
		public string|null $description = null,
		public array $variables = [],
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = ['url' => $this->url];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->variables !== []) {
			$vars = [];

			foreach ($this->variables as $variable) {
				$vars[$variable->getName()] = $variable->toOpenApiSpec();
			}

			$spec['variables'] = $vars;
		}

		return $spec;
	}

}
