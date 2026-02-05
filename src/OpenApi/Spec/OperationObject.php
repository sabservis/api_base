<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;
use function array_keys;
use function count;
use function is_array;
use function range;

final class OperationObject implements JsonSerializable
{

	/**
	 * @param array<string>|null $tags
	 * @param array<ParameterObject>|null $parameters
	 * @param array<string, ResponseObject> $responses
	 * @param array<array<string, array<string>>>|null $security
	 * @param array<ServerObject>|null $servers
	 * @param array<string, mixed>|null $extras Extra OpenAPI spec to deep-merge (takes precedence)
	 */
	public function __construct(
		public array $responses,
		public array|null $tags = null,
		public string|null $summary = null,
		public string|null $description = null,
		public string|null $operationId = null,
		public array|null $parameters = null,
		public RequestBodyObject|null $requestBody = null,
		public bool|null $deprecated = null,
		public array|null $security = null,
		public array|null $servers = null,
		public ExternalDocsObject|null $externalDocs = null,
		public array|null $extras = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = [];

		if ($this->tags !== null && $this->tags !== []) {
			$data['tags'] = $this->tags;
		}

		if ($this->summary !== null) {
			$data['summary'] = $this->summary;
		}

		if ($this->description !== null) {
			$data['description'] = $this->description;
		}

		if ($this->operationId !== null) {
			$data['operationId'] = $this->operationId;
		}

		if ($this->parameters !== null && $this->parameters !== []) {
			$data['parameters'] = $this->parameters;
		}

		if ($this->requestBody !== null) {
			$data['requestBody'] = $this->requestBody;
		}

		$data['responses'] = $this->responses;

		if ($this->deprecated !== null) {
			$data['deprecated'] = $this->deprecated;
		}

		if ($this->security !== null) {
			$data['security'] = $this->security;
		}

		if ($this->servers !== null && $this->servers !== []) {
			$data['servers'] = $this->servers;
		}

		if ($this->externalDocs !== null) {
			$data['externalDocs'] = $this->externalDocs;
		}

		// Deep merge extras (extras take precedence)
		if ($this->extras !== null) {
			$data = $this->deepMerge($data, $this->extras);
		}

		return $data;
	}

	/**
	 * Deep merge arrays. Values from $override take precedence.
	 *
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $override
	 * @return array<string, mixed>
	 */
	private function deepMerge(array $base, array $override): array
	{
		foreach ($override as $key => $value) {
			// phpcs:ignore SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed
			if (
				is_array($value)
				&& isset($base[$key])
				&& is_array($base[$key])
				&& $this->isAssociativeArray($value)
				&& $this->isAssociativeArray($base[$key])
			) {
				// Both are associative arrays - merge recursively
				$base[$key] = $this->deepMerge($base[$key], $value);
			} else {
				// Override value (including indexed arrays - replace, don't append)
				$base[$key] = $value;
			}
		}

		return $base;
	}

	/**
	 * Check if array is associative (has string keys).
	 *
	 * @param array<mixed> $array
	 */
	private function isAssociativeArray(array $array): bool
	{
		if ($array === []) {
			return false;
		}

		return array_keys($array) !== range(0, count($array) - 1);
	}

}
