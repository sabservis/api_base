<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use InvalidArgumentException;
use JsonSerializable;
use function sprintf;
use function strtolower;

final class PathItemObject implements JsonSerializable
{

	/**
	 * @param array<ParameterObject>|null $parameters
	 * @param array<ServerObject>|null $servers
	 */
	public function __construct(
		public string|null $ref = null,
		public string|null $summary = null,
		public string|null $description = null,
		public OperationObject|null $get = null,
		public OperationObject|null $put = null,
		public OperationObject|null $post = null,
		public OperationObject|null $delete = null,
		public OperationObject|null $options = null,
		public OperationObject|null $head = null,
		public OperationObject|null $patch = null,
		public OperationObject|null $trace = null,
		public array|null $parameters = null,
		public array|null $servers = null,
	)
	{
	}

	/**
	 * Returns a new PathItemObject with the given HTTP method set.
	 *
	 * @throws InvalidArgumentException If method is not a valid HTTP method
	 */
	public function withMethod(string $method, OperationObject $operation): self
	{
		$new = clone $this;
		$methodLower = strtolower($method);

		match ($methodLower) {
			'get' => $new->get = $operation,
			'post' => $new->post = $operation,
			'put' => $new->put = $operation,
			'patch' => $new->patch = $operation,
			'delete' => $new->delete = $operation,
			'head' => $new->head = $operation,
			'options' => $new->options = $operation,
			'trace' => $new->trace = $operation,
			default => throw new InvalidArgumentException(
				sprintf(
					'Invalid HTTP method "%s". Allowed: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS, TRACE',
					$method,
				),
			),
		};

		return $new;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		if ($this->ref !== null) {
			return ['$ref' => $this->ref];
		}

		$data = [];

		if ($this->summary !== null) {
			$data['summary'] = $this->summary;
		}

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->get !== null) {
			$data['get'] = $this->get;
		}

		if ($this->put !== null) {
			$data['put'] = $this->put;
		}

		if ($this->post !== null) {
			$data['post'] = $this->post;
		}

		if ($this->delete !== null) {
			$data['delete'] = $this->delete;
		}

		if ($this->options !== null) {
			$data['options'] = $this->options;
		}

		if ($this->head !== null) {
			$data['head'] = $this->head;
		}

		if ($this->patch !== null) {
			$data['patch'] = $this->patch;
		}

		if ($this->trace !== null) {
			$data['trace'] = $this->trace;
		}

		if ($this->parameters !== null && $this->parameters !== []) {
			$data['parameters'] = $this->parameters;
		}

		if ($this->servers !== null && $this->servers !== []) {
			$data['servers'] = $this->servers;
		}

		return $data;
	}

}
