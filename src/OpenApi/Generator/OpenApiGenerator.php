<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Generator;

use Closure;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\OpenApi\Spec\ComponentsObject;
use Sabservis\Api\OpenApi\Spec\ContactObject;
use Sabservis\Api\OpenApi\Spec\InfoObject;
use Sabservis\Api\OpenApi\Spec\LicenseObject;
use Sabservis\Api\OpenApi\Spec\MediaTypeObject;
use Sabservis\Api\OpenApi\Spec\OpenApiSpec;
use Sabservis\Api\OpenApi\Spec\OperationObject;
use Sabservis\Api\OpenApi\Spec\ParameterObject;
use Sabservis\Api\OpenApi\Spec\PathItemObject;
use Sabservis\Api\OpenApi\Spec\RequestBodyObject;
use Sabservis\Api\OpenApi\Spec\ResponseObject;
use Sabservis\Api\OpenApi\Spec\SchemaObject;
use Sabservis\Api\OpenApi\Spec\SecuritySchemeObject;
use Sabservis\Api\OpenApi\Spec\ServerObject;
use Sabservis\Api\OpenApi\Spec\TagObject;
use Sabservis\Api\OpenApi\TypeMapper;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\Schema;
use function array_map;
use function array_unique;
use function array_values;
use function array_walk_recursive;
use function class_exists;
use function end;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function lcfirst;
use function preg_match;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function strlen;
use function substr;
use function ucfirst;
use function usort;
use const JSON_THROW_ON_ERROR;

final class OpenApiGenerator
{

	/**
	 * Maximum JSON decode depth for OpenAPI spec processing.
	 */
	private const JSON_DECODE_DEPTH = 512;

	private SchemaBuilder $schemaBuilder;

	/** @var array<string, TagObject> */
	private array $tags = [];

	/** @var array<string> */
	private array $usedTags = [];

	/**
	 * @param Closure(Endpoint): bool|null $endpointFilter Filter endpoints - return true to include
	 * @param Closure(string): bool|null $schemaFilter Filter schemas by class name - return true to include
	 */
	public function __construct(
		private OpenApiConfig $config,
		private Closure|null $endpointFilter = null,
		private Closure|null $schemaFilter = null,
	)
	{
		$this->schemaBuilder = new SchemaBuilder($this->schemaFilter);
	}

	public function generate(Schema $schema): OpenApiSpec
	{
		// Reset state for fresh generation
		$this->tags = [];
		$this->usedTags = [];
		$this->schemaBuilder = new SchemaBuilder($this->schemaFilter);

		$paths = $this->buildPaths($schema);
		$components = $this->buildComponents($paths);

		// Filter tags to only include used ones
		$filteredTags = $this->filterUnusedTags();

		return new OpenApiSpec(
			openapi: '3.0.3',
			info: $this->buildInfo(),
			servers: $this->buildServers(),
			paths: $paths,
			components: $components,
			security: $this->buildSecurity(),
			tags: $filteredTags !== [] ? $filteredTags : null,
		);
	}

	public function generateJson(Schema $schema): string
	{
		return $this->generate($schema)->toPrettyJson();
	}

	private function buildInfo(): InfoObject
	{
		$contact = null;

		if (
			$this->config->contactName !== null
			|| $this->config->contactEmail !== null
			|| $this->config->contactUrl !== null
		) {
			$contact = new ContactObject(
				name: $this->config->contactName,
				url: $this->config->contactUrl,
				email: $this->config->contactEmail,
			);
		}

		$license = null;

		if ($this->config->licenseName !== null) {
			$license = new LicenseObject(
				name: $this->config->licenseName,
				url: $this->config->licenseUrl,
			);
		}

		return new InfoObject(
			title: $this->config->title,
			version: $this->config->version,
			description: $this->config->description,
			termsOfService: $this->config->termsOfService,
			contact: $contact,
			license: $license,
		);
	}

	/**
	 * @return array<ServerObject>|null
	 */
	private function buildServers(): array|null
	{
		if ($this->config->servers === []) {
			return null;
		}

		$servers = [];

		foreach ($this->config->servers as $server) {
			$servers[] = new ServerObject(
				url: $server['url'],
				description: $server['description'] ?? null,
			);
		}

		return $servers;
	}

	/**
	 * @return array<string, PathItemObject>
	 */
	private function buildPaths(Schema $schema): array
	{
		$paths = [];

		foreach ($schema->getEndpoints() as $endpoint) {
			$mask = $endpoint->getMask();

			if ($mask === null) {
				continue;
			}

			// Apply endpoint filter
			if ($this->endpointFilter !== null && ($this->endpointFilter)($endpoint) === false) {
				continue;
			}

			// Convert mask to OpenAPI path format (e.g., {id} instead of <id>)
			$path = $this->convertMaskToPath($mask);

			if (!isset($paths[$path])) {
				$paths[$path] = new PathItemObject();
			}

			$operation = $this->buildOperation($endpoint, $schema);

			foreach ($endpoint->getMethods() as $method) {
				$paths[$path] = $paths[$path]->withMethod($method, $operation);
			}
		}

		return $paths;
	}

	private function buildOperation(Endpoint $endpoint, Schema $schema): OperationObject
	{
		$parameters = $this->buildParameters($endpoint);
		$requestBody = $this->buildRequestBody($endpoint);
		$responses = $this->buildResponses($endpoint);
		$tags = $this->extractTags($endpoint, $schema);

		// Build operation ID from handler
		$operationId = $endpoint->getTag(Endpoint::TAG_ID);

		if ($operationId === null) {
			$classParts = explode('\\', $endpoint->getControllerClass());
			$className = end($classParts);
			$className = str_replace('Controller', '', $className);
			$operationId = lcfirst($className) . ucfirst($endpoint->getControllerMethod());
		}

		// Get custom OpenAPI spec from #[OpenApiMerge] attribute
		$extras = $endpoint->getOpenApi();

		// Get summary and deprecated from Operation attribute
		$summary = $endpoint->getSummary();
		$deprecated = $endpoint->isDeprecated() ? true : null;

		return new OperationObject(
			responses: $responses,
			tags: $tags !== [] ? $tags : null,
			summary: $summary,
			operationId: $operationId,
			parameters: $parameters !== [] ? $parameters : null,
			requestBody: $requestBody,
			deprecated: $deprecated,
			extras: $extras !== [] ? $extras : null,
		);
	}

	/**
	 * @return array<ParameterObject>
	 */
	private function buildParameters(Endpoint $endpoint): array
	{
		$parameters = [];

		foreach ($endpoint->getParameters() as $param) {
			$schema = $this->buildParameterSchema($param);

			$parameters[] = new ParameterObject(
				name: $param->getName(),
				in: $param->getIn(),
				description: $param->getDescription(),
				required: $param->getIn() === EndpointParameter::InPath ? true : ($param->isRequired() ?: null),
				deprecated: $param->isDeprecated() ?: null,
				allowEmptyValue: $param->isAllowEmptyValue() ?: null,
				schema: $schema,
			);
		}

		return $parameters;
	}

	private function buildParameterSchema(EndpointParameter $param): SchemaObject
	{
		$type = $param->getType();

		// DateTime types should be mapped to string with format, not as $ref
		if (TypeMapper::isDateType($type)) {
			$openApiType = TypeMapper::phpToOpenApi($type);

			return new SchemaObject(
				type: $openApiType['type'],
				format: $openApiType['format'] ?? null,
			);
		}

		// Check if it's a class reference (but not DateTime)
		if (class_exists($type)) {
			return $this->schemaBuilder->getReference($type);
		}

		// Map type to OpenAPI
		$openApiType = TypeMapper::phpToOpenApi($type);

		return new SchemaObject(
			type: $openApiType['type'],
			format: $openApiType['format'] ?? null,
		);
	}

	private function buildRequestBody(Endpoint $endpoint): RequestBodyObject|null
	{
		$requestBody = $endpoint->getRequestBody();

		if ($requestBody === null) {
			return null;
		}

		$entity = $requestBody->getEntity();

		// If we have an entity class reference, use it
		if ($entity !== null && class_exists($entity)) {
			// Register the entity class
			$schemaName = $this->schemaBuilder->registerClass($entity);

			return new RequestBodyObject(
				content: [
					'application/json' => new MediaTypeObject(
						schema: new SchemaObject(ref: $schemaName),
					),
				],
				description: $requestBody->getDescription() ?: null,
				required: $requestBody->isRequired() ?: null,
			);
		}

		// If we have an inline content spec, use it
		$contentSpec = $requestBody->getContentSpec();

		if ($contentSpec !== null) {
			$content = [];

			foreach ($contentSpec as $mediaType => $spec) {
				$schema = null;

				if (isset($spec['schema'])) {
					$schema = $this->buildSchemaFromSpec($spec['schema']);
				}

				$content[$mediaType] = new MediaTypeObject(schema: $schema);
			}

			return new RequestBodyObject(
				content: $content,
				description: $requestBody->getDescription() ?: null,
				required: $requestBody->isRequired() ?: null,
			);
		}

		return null;
	}

	/**
	 * Build a SchemaObject from a raw OpenAPI spec array.
	 *
	 * @param array<string, mixed> $spec
	 */
	private function buildSchemaFromSpec(array $spec): SchemaObject
	{
		$items = null;

		if (isset($spec['items'])) {
			$items = $this->buildSchemaFromSpec($spec['items']);
		}

		// Handle properties for object types
		$properties = null;

		if (isset($spec['properties']) && is_array($spec['properties'])) {
			$properties = [];

			foreach ($spec['properties'] as $name => $propertySpec) {
				$properties[$name] = $this->buildSchemaFromSpec($propertySpec);
			}
		}

		// Handle $ref - strip prefix if already present (since SchemaObject adds it during serialization)
		$ref = $spec['$ref'] ?? null;

		if ($ref !== null && str_starts_with($ref, '#/components/schemas/')) {
			$ref = substr($ref, strlen('#/components/schemas/'));
		}

		// Handle oneOf/anyOf/allOf composition
		$oneOf = null;
		$anyOf = null;
		$allOf = null;

		if (isset($spec['oneOf']) && is_array($spec['oneOf'])) {
			$oneOf = array_map(fn (array $s): SchemaObject => $this->buildSchemaFromSpec($s), $spec['oneOf']);
		}

		if (isset($spec['anyOf']) && is_array($spec['anyOf'])) {
			$anyOf = array_map(fn (array $s): SchemaObject => $this->buildSchemaFromSpec($s), $spec['anyOf']);
		}

		if (isset($spec['allOf']) && is_array($spec['allOf'])) {
			$allOf = array_map(fn (array $s): SchemaObject => $this->buildSchemaFromSpec($s), $spec['allOf']);
		}

		return new SchemaObject(
			type: $spec['type'] ?? null,
			format: $spec['format'] ?? null,
			description: $spec['description'] ?? null,
			properties: $properties,
			required: $spec['required'] ?? null,
			items: $items,
			example: $spec['example'] ?? null,
			ref: $ref,
			oneOf: $oneOf,
			anyOf: $anyOf,
			allOf: $allOf,
		);
	}

	/**
	 * @return array<string, ResponseObject>
	 */
	private function buildResponses(Endpoint $endpoint): array
	{
		/** @var array<string, ResponseObject> $responses */
		$responses = [];

		foreach ($endpoint->getResponses() as $response) {
			$code = $response->getCode();
			$entity = $response->getEntity();
			$content = null;

			// File response
			if ($response->isFileResponse()) {
				$content = [
					$response->getFileContentType() => new MediaTypeObject(
						schema: new SchemaObject(type: 'string', format: 'binary'),
					),
				];
			} elseif ($entity !== null && class_exists($entity)) {
				$schemaName = $this->schemaBuilder->registerClass($entity);

				if ($response->isListWithMeta()) {
					$schema = $this->buildListWithMetaSchema($schemaName);
				} elseif ($response->isListNoMeta()) {
					$schema = $this->buildListNoMetaSchema($schemaName);
				} else {
					$schema = new SchemaObject(ref: $schemaName);
				}

				$content = [
					'application/json' => new MediaTypeObject(schema: $schema),
				];
			} elseif ($response->getContentSpec() !== null) {
				// Use inline content spec (for oneOf, anyOf, custom schemas)
				$contentSpec = $response->getContentSpec();
				$content = [];

				// Register any referenced schemas first
				foreach ($response->getReferencedSchemas() as $className) {
					if (class_exists($className)) {
						$this->schemaBuilder->registerClass($className);
					}
				}

				foreach ($contentSpec as $mediaType => $spec) {
					$schema = null;

					if (isset($spec['schema'])) {
						$schema = $this->buildSchemaFromSpec($spec['schema']);
					}

					$content[$mediaType] = new MediaTypeObject(schema: $schema);
				}
			}

			$responses[(string) $code] = new ResponseObject(
				description: $response->getDescription() ?: $this->getDefaultResponseDescription($code),
				content: $content,
			);
		}

		// Add default 200 response if none defined
		if ($responses === []) {
			$responses['200'] = new ResponseObject(description: 'OK');
		}

		return $responses; // @phpstan-ignore-line
	}

	/**
	 * Build inline schema for list with meta wrapper.
	 * Structure: { data: [], meta: { total, limit, offset } }
	 */
	private function buildListWithMetaSchema(string $schemaName): SchemaObject
	{
		return new SchemaObject(
			type: 'object',
			properties: [
				'data' => new SchemaObject(
					type: 'array',
					items: new SchemaObject(ref: $schemaName),
				),
				'meta' => new SchemaObject(
					type: 'object',
					properties: [
						'total' => new SchemaObject(type: 'integer'),
						'limit' => new SchemaObject(type: 'integer'),
						'offset' => new SchemaObject(type: 'integer'),
					],
					required: ['total', 'limit', 'offset'],
				),
			],
			required: ['data', 'meta'],
		);
	}

	/**
	 * Build inline schema for list without meta wrapper.
	 * Structure: array of items
	 */
	private function buildListNoMetaSchema(string $schemaName): SchemaObject
	{
		return new SchemaObject(
			type: 'array',
			items: new SchemaObject(ref: $schemaName),
		);
	}

	/**
	 * @return array<string>
	 */
	private function extractTags(Endpoint $endpoint, Schema $schema): array
	{
		$tags = [];
		$schemaTags = $schema->getTags();

		foreach ($endpoint->getTags() as $name => $value) {
			if (is_string($name) && $name !== Endpoint::TAG_ID) {
				$tags[] = $name;

				// Register tag if not already registered
				if (!isset($this->tags[$name])) {
					// Get description from schema tags first, then from config
					$description = $schemaTags[$name]['description'] ?? $this->config->tags[$name] ?? null;
					$this->tags[$name] = new TagObject(name: $name, description: $description);
				}

				// Track as used
				$this->usedTags[] = $name;
			}
		}

		return $tags;
	}

	/**
	 * @param array<string, PathItemObject> $paths
	 */
	private function buildComponents(array $paths): ComponentsObject|null
	{
		$allSchemas = $this->schemaBuilder->getSchemas();
		$securitySchemes = $this->buildSecuritySchemes();

		// Filter unused schemas
		$schemas = $this->filterUnusedSchemas($allSchemas, $paths);

		if ($schemas === [] && $securitySchemes === null) {
			return null;
		}

		return new ComponentsObject(
			schemas: $schemas !== [] ? $schemas : null,
			securitySchemes: $securitySchemes,
		);
	}

	/**
	 * Find all schema references used in paths and filter out unused ones.
	 *
	 * @param array<string, SchemaObject> $schemas
	 * @param array<string, PathItemObject> $paths
	 * @return array<string, SchemaObject>
	 */
	private function filterUnusedSchemas(array $schemas, array $paths): array
	{
		// Convert paths to array for reference scanning
		$pathsJson = json_encode($paths, JSON_THROW_ON_ERROR);
		$pathsData = json_decode($pathsJson, true, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);

		// Find all directly referenced schemas
		$directReferences = $this->findAllReferences($pathsData);

		// Expand with dependencies (schemas that reference other schemas)
		$schemasJson = json_encode($schemas, JSON_THROW_ON_ERROR);
		$schemasData = json_decode($schemasJson, true, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);
		$allUsedSchemas = $this->expandWithDependencies($directReferences, $schemasData);

		// Filter schemas to only include used ones
		$filtered = [];

		foreach ($schemas as $name => $schema) {
			if (in_array($name, $allUsedSchemas, true)) {
				$filtered[$name] = $schema;
			}
		}

		return $filtered;
	}

	/**
	 * Find all $ref references in the data structure.
	 *
	 * @param array<mixed> $data
	 * @return array<string>
	 */
	private function findAllReferences(array $data): array
	{
		$references = [];

		array_walk_recursive($data, static function (mixed $value, string|int $key) use (&$references): void {
			if ($key !== '$ref' || !is_string($value)) {
				return;
			}

			if (preg_match('/#\/components\/schemas\/(\w+)/', $value, $matches) !== 1) {
				return;
			}

			$references[] = $matches[1];
		});

		return array_values(array_unique($references));
	}

	/**
	 * Expand references with their dependencies (referenced schemas that reference other schemas).
	 *
	 * @param array<string> $references
	 * @param array<string, array<mixed>> $schemas
	 * @return array<string>
	 */
	private function expandWithDependencies(array $references, array $schemas): array
	{
		$allUsed = [];

		foreach ($references as $reference) {
			$this->addSchemaWithDependencies($reference, $schemas, $allUsed);
		}

		return $allUsed;
	}

	/**
	 * Recursively add a schema and all its dependencies.
	 *
	 * @param array<string, array<mixed>> $schemas
	 * @param array<string> $allUsed
	 */
	private function addSchemaWithDependencies(string $schemaName, array $schemas, array &$allUsed): void
	{
		if (in_array($schemaName, $allUsed, true)) {
			return;
		}

		$allUsed[] = $schemaName;

		if (!isset($schemas[$schemaName])) {
			return;
		}

		// Find all references within this schema
		$schemaReferences = $this->findAllReferences($schemas[$schemaName]);

		foreach ($schemaReferences as $reference) {
			$this->addSchemaWithDependencies($reference, $schemas, $allUsed);
		}
	}

	/**
	 * Filter tags to only include ones that are actually used.
	 *
	 * @return array<TagObject>
	 */
	private function filterUnusedTags(): array
	{
		$usedTags = array_unique($this->usedTags);
		$filtered = [];

		foreach ($this->tags as $name => $tag) {
			if (in_array($name, $usedTags, true)) {
				$filtered[] = $tag;
			}
		}

		// Sort by name
		usort($filtered, static fn (TagObject $a, TagObject $b) => strcmp($a->name, $b->name));

		return $filtered;
	}

	/**
	 * @return array<string, SecuritySchemeObject>|null
	 */
	private function buildSecuritySchemes(): array|null
	{
		if ($this->config->securitySchemes === []) {
			return null;
		}

		$schemes = [];

		foreach ($this->config->securitySchemes as $name => $config) {
			$schemes[$name] = new SecuritySchemeObject(
				type: $config['type'] ?? 'http',
				description: $config['description'] ?? null,
				name: $config['name'] ?? null,
				in: $config['in'] ?? null,
				scheme: $config['scheme'] ?? null,
				bearerFormat: $config['bearerFormat'] ?? null,
			);
		}

		return $schemes;
	}

	/**
	 * @return array<array<string, array<string>>>|null
	 */
	private function buildSecurity(): array|null
	{
		if ($this->config->defaultSecurity === []) {
			return null;
		}

		return $this->config->defaultSecurity;
	}

	private function convertMaskToPath(string $mask): string
	{
		// Convert <param> to {param}
		return preg_replace('/<([^>]+)>/', '{$1}', $mask) ?? $mask;
	}

	private function getDefaultResponseDescription(string $code): string
	{
		return match ($code) {
			'200' => 'OK',
			'201' => 'Created',
			'204' => 'No Content',
			'400' => 'Bad Request',
			'401' => 'Unauthorized',
			'403' => 'Forbidden',
			'404' => 'Not Found',
			'422' => 'Unprocessable Entity',
			'500' => 'Internal Server Error',
			default => 'Response',
		};
	}

}
