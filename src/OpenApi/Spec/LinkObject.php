<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class LinkObject implements JsonSerializable
{

	/**
	 * @param array<string, mixed>|null $parameters
	 */
	public function __construct(
		public string|null $operationRef = null,
		public string|null $operationId = null,
		public array|null $parameters = null,
		public mixed $requestBody = null,
		public string|null $description = null,
		public ServerObject|null $server = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->operationRef !== null) {
			$data['operationRef'] = $this->operationRef;
		}

		if ($this->operationId !== null) {
			$data['operationId'] = $this->operationId;
		}

		if ($this->parameters !== null) {
			$data['parameters'] = $this->parameters;
		}

		if ($this->requestBody !== null) {
			$data['requestBody'] = $this->requestBody;
		}

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->server !== null) {
			$data['server'] = $this->server;
		}

		return $data;
	}

}
