<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

/**
 * Base class for HTTP method attributes (Get, Post, Put, Patch, Delete).
 *
 * Provides minimal, focused API for defining endpoints:
 * - path: URL pattern (e.g., '/users/{id}')
 * - summary: Short description for OpenAPI docs
 * - description: Detailed description (supports Markdown)
 * - operationId: Unique ID for generated API clients
 * - deprecated: Mark endpoint as deprecated
 *
 * For other OpenAPI features, use dedicated attributes:
 * - Tags: #[Tag('users')]
 * - Parameters: Inferred from method signature or #[QueryParameter], #[PathParameter]
 * - Request body: Inferred from DTO parameter or #[RequestBody]
 * - Responses: #[Response]
 */
abstract class Operation implements OpenApiAttributeInterface, RequestOperationAttribute
{

	/**
	 * @param string|null $path URL path pattern (e.g., '/users/{id}')
	 * @param string|null $summary Short description for OpenAPI documentation
	 * @param string|null $description Detailed description for OpenAPI documentation (supports Markdown)
	 * @param string|null $operationId Unique identifier for generated API clients
	 * @param bool $deprecated Mark this endpoint as deprecated
	 * @param array<array<string, array<string>>>|null $security Security requirements (null = inherit, [] = public)
	 */
	public function __construct(
		public string|null $path = null,
		public string|null $summary = null,
		public string|null $description = null,
		public string|null $operationId = null,
		public bool $deprecated = false,
		public array|null $security = null,
	)
	{
	}

	abstract public function getMethod(): string;

	public function getPath(): string|null
	{
		return $this->path;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->operationId !== null) {
			$spec['operationId'] = $this->operationId;
		}

		if ($this->summary !== null) {
			$spec['summary'] = $this->summary;
		}

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		if ($this->deprecated) {
			$spec['deprecated'] = true;
		}

		return $spec;
	}

}
