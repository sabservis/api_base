<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ComponentsObject implements JsonSerializable
{

	/**
	 * @param array<string, SchemaObject>|null $schemas
	 * @param array<string, ResponseObject>|null $responses
	 * @param array<string, ParameterObject>|null $parameters
	 * @param array<string, RequestBodyObject>|null $requestBodies
	 * @param array<string, HeaderObject>|null $headers
	 * @param array<string, SecuritySchemeObject>|null $securitySchemes
	 * @param array<string, LinkObject>|null $links
	 */
	public function __construct(
		public array|null $schemas = null,
		public array|null $responses = null,
		public array|null $parameters = null,
		public array|null $requestBodies = null,
		public array|null $headers = null,
		public array|null $securitySchemes = null,
		public array|null $links = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->schemas !== null && $this->schemas !== []) {
			$data['schemas'] = $this->schemas;
		}

		if ($this->responses !== null && $this->responses !== []) {
			$data['responses'] = $this->responses;
		}

		if ($this->parameters !== null && $this->parameters !== []) {
			$data['parameters'] = $this->parameters;
		}

		if ($this->requestBodies !== null && $this->requestBodies !== []) {
			$data['requestBodies'] = $this->requestBodies;
		}

		if ($this->headers !== null && $this->headers !== []) {
			$data['headers'] = $this->headers;
		}

		if ($this->securitySchemes !== null && $this->securitySchemes !== []) {
			$data['securitySchemes'] = $this->securitySchemes;
		}

		if ($this->links !== null && $this->links !== []) {
			$data['links'] = $this->links;
		}

		return $data;
	}

}
