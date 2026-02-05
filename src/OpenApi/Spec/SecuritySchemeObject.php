<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class SecuritySchemeObject implements JsonSerializable
{

	public const TypeApiKey = 'apiKey';

	public const TypeHttp = 'http';

	public const TypeOAuth2 = 'oauth2';

	public const TypeOpenIdConnect = 'openIdConnect';

	/**
	 * @param array<string, OAuthFlowObject>|null $flows
	 */
	public function __construct(
		public string $type,
		public string|null $description = null,
		public string|null $name = null,
		public string|null $in = null,
		public string|null $scheme = null,
		public string|null $bearerFormat = null,
		public array|null $flows = null,
		public string|null $openIdConnectUrl = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['type' => $this->type];

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->name !== null) {
			$data['name'] = $this->name;
		}

		if ($this->in !== null) {
			$data['in'] = $this->in;
		}

		if ($this->scheme !== null) {
			$data['scheme'] = $this->scheme;
		}

		if ($this->bearerFormat !== null) {
			$data['bearerFormat'] = $this->bearerFormat;
		}

		if ($this->flows !== null) {
			$data['flows'] = $this->flows;
		}

		if ($this->openIdConnectUrl !== null) {
			$data['openIdConnectUrl'] = $this->openIdConnectUrl;
		}

		return $data;
	}

}
