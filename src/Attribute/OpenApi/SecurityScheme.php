<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class SecurityScheme implements OpenApiAttributeInterface
{

	public const TypeApiKey = 'apiKey';

	public const TypeHttp = 'http';

	public const TypeOAuth2 = 'oauth2';

	public const TypeOpenIdConnect = 'openIdConnect';

	public function __construct(
		public string $securityScheme,
		public string $type,
		public string|null $description = null,
		public string|null $name = null,
		public string|null $in = null,
		public string|null $scheme = null,
		public string|null $bearerFormat = null,
		public string|null $openIdConnectUrl = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = ['type' => $this->type];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->name !== null) {
			$spec['name'] = $this->name;
		}

		if ($this->in !== null) {
			$spec['in'] = $this->in;
		}

		if ($this->scheme !== null) {
			$spec['scheme'] = $this->scheme;
		}

		if ($this->bearerFormat !== null) {
			$spec['bearerFormat'] = $this->bearerFormat;
		}

		if ($this->openIdConnectUrl !== null) {
			$spec['openIdConnectUrl'] = $this->openIdConnectUrl;
		}

		return $spec;
	}

}
