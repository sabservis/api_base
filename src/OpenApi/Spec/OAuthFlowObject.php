<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class OAuthFlowObject implements JsonSerializable
{

	/**
	 * @param array<string, string> $scopes
	 */
	public function __construct(
		public array $scopes,
		public string|null $authorizationUrl = null,
		public string|null $tokenUrl = null,
		public string|null $refreshUrl = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->authorizationUrl !== null) {
			$data['authorizationUrl'] = $this->authorizationUrl;
		}

		if ($this->tokenUrl !== null) {
			$data['tokenUrl'] = $this->tokenUrl;
		}

		if ($this->refreshUrl !== null) {
			$data['refreshUrl'] = $this->refreshUrl;
		}

		$data['scopes'] = $this->scopes;

		return $data;
	}

}
