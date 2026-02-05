<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class OpenApiSpec implements JsonSerializable
{

	/**
	 * @param array<string, PathItemObject> $paths
	 * @param array<ServerObject>|null $servers
	 * @param array<TagObject>|null $tags
	 * @param array<array<string, array<string>>>|null $security
	 */
	public function __construct(
		public string $openapi = '3.0.3',
		public InfoObject|null $info = null,
		public string|null $jsonSchemaDialect = null,
		public array|null $servers = null,
		public array $paths = [],
		public ComponentsObject|null $components = null,
		public array|null $security = null,
		public array|null $tags = null,
		public ExternalDocsObject|null $externalDocs = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['openapi' => $this->openapi];

		if ($this->info !== null) {
			$data['info'] = $this->info;
		}

		if ($this->jsonSchemaDialect !== null) {
			$data['jsonSchemaDialect'] = $this->jsonSchemaDialect;
		}

		if ($this->servers !== null && $this->servers !== []) {
			$data['servers'] = $this->servers;
		}

		if ($this->paths !== []) {
			$data['paths'] = $this->paths;
		}

		if ($this->components !== null) {
			$componentsData = $this->components->jsonSerialize();

			if ($componentsData !== []) {
				$data['components'] = $this->components;
			}
		}

		if ($this->security !== null && $this->security !== []) {
			$data['security'] = $this->security;
		}

		if ($this->tags !== null && $this->tags !== []) {
			$data['tags'] = $this->tags;
		}

		if ($this->externalDocs !== null) {
			$data['externalDocs'] = $this->externalDocs;
		}

		return $data;
	}

	public function toJson(int $flags = 0): string
	{
		return json_encode($this, $flags | JSON_THROW_ON_ERROR);
	}

	public function toPrettyJson(): string
	{
		return $this->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

}
