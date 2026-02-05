<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use ReflectionClass;
use ReflectionMethod;
use Sabservis\Api\Attribute\OpenApi\Alias;
use Sabservis\Api\Attribute\OpenApi\Hidden;
use Sabservis\Api\Attribute\OpenApi\OpenApiMerge;
use Sabservis\Api\Attribute\OpenApi\Operation;
use Sabservis\Api\Attribute\OpenApi\RequestOperationAttribute;
use Sabservis\Api\Attribute\OpenApi\RequestParameter;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\UI\Controller\Controller as ControllerInterface;
use Sabservis\Api\Utils\Regex;
use function array_filter;
use function array_merge;
use function assert;
use function class_exists;
use function class_parents;
use function implode;
use function is_string;
use function is_subclass_of;
use function preg_replace;
use function sprintf;
use function strtoupper;
use function trim;
use function usort;

/**
 * Loader for OpenAPI attributes - generates schema array directly from PHP attributes.
 *
 * This class orchestrates the loading process, delegating specific tasks to:
 * - ParameterInferenceEngine: Parameter inference from method signatures and DTOs
 * - RequestBodyBuilder: Request body specification building
 * - ResponseBuilder: Response specification building
 */
final class OpenApiAttributeLoader
{

	/** @var array<string, array{parents: array<string, ReflectionClass<object>>, reflection: ReflectionClass<object>}> */
	private array $meta = [];

	/** @var array<string, array{name: string, description: string|null}> */
	private array $tags = [];

	private ParameterInferenceEngine $parameterEngine;

	private RequestBodyBuilder $requestBodyBuilder;

	private ResponseBuilder $responseBuilder;

	public function __construct(private ContainerBuilder $containerBuilder)
	{
		$this->parameterEngine = new ParameterInferenceEngine();
		$this->requestBodyBuilder = new RequestBodyBuilder();
		$this->responseBuilder = new ResponseBuilder();
	}

	/**
	 * Load schema from controller attributes and return as array.
	 *
	 * @return array{endpoints: array<mixed>, tags: array<string, array{name: string, description: string|null}>}
	 */
	public function load(): array
	{
		$endpoints = [];
		$controllers = $this->findControllers();

		foreach ($controllers as $def) {
			$type = $def->getType();

			if ($type === null) {
				throw new InvalidStateException(
					'Cannot analyse class with no type defined. Make sure all controllers have defined their class.',
				);
			}

			if (!class_exists($type)) {
				throw new InvalidStateException(sprintf('Class %s does not exist', $type));
			}

			$class = $this->analyseClass($type);

			if (!$this->acceptController($class)) {
				continue;
			}

			$controllerEndpoints = $this->parseController($type, $class);
			$endpoints = array_merge($endpoints, $controllerEndpoints);
		}

		// Sort endpoints by mask for consistent output
		usort($endpoints, static fn (array $a, array $b): int => $a['mask'] <=> $b['mask']);

		return [
			'endpoints' => $endpoints,
			'tags' => $this->tags,
		];
	}

	/**
	 * @return array<ServiceDefinition>
	 */
	private function findControllers(): array
	{
		/** @var array<ServiceDefinition> */
		return $this->containerBuilder->findByType(ControllerInterface::class);
	}

	/**
	 * @return ReflectionClass<object>
	 */
	private function analyseClass(string $class): ReflectionClass
	{
		if (isset($this->meta[$class])) {
			return $this->meta[$class]['reflection'];
		}

		if (!class_exists($class)) {
			throw new InvalidStateException(sprintf('Class %s does not exist', $class));
		}

		/** @var ReflectionClass<object> $classRef */
		$classRef = new ReflectionClass($class);

		$this->meta[$class] = [
			'parents' => [],
			'reflection' => $classRef,
		];

		/** @var array<string> $parents */
		$parents = class_parents($class);
		$reflections = [];

		foreach ($parents as $parentClass) {
			if (isset($this->meta[$parentClass])) {
				$reflections[$parentClass] = $this->meta[$parentClass]['reflection'];

				continue;
			}

			if (!class_exists($parentClass)) {
				continue;
			}

			/** @var ReflectionClass<object> $parentClassRf */
			$parentClassRf = new ReflectionClass($parentClass);
			$reflections[$parentClass] = $parentClassRf;

			$this->meta[$parentClass] = [
				'parents' => [],
				'reflection' => $parentClassRf,
			];

			$this->analyseClass($parentClass);
		}

		$this->meta[$class]['parents'] = $reflections;

		return $classRef;
	}

	/**
	 * @param ReflectionClass<object> $class
	 */
	private function acceptController(ReflectionClass $class): bool
	{
		return is_subclass_of($class->getName(), ControllerInterface::class);
	}

	/**
	 * @param class-string $controllerClass
	 * @param ReflectionClass<object> $reflectionClass
	 * @return array<array<mixed>>
	 */
	private function parseController(string $controllerClass, ReflectionClass $reflectionClass): array
	{
		$endpoints = [];
		$controllerTags = [];
		$controllerPath = '';

		// Parse class-level tags
		foreach ($reflectionClass->getAttributes(Tag::class) as $attribute) {
			$tag = $attribute->newInstance();
			assert($tag instanceof Tag);
			$controllerTags[$tag->getName()] = 1;

			// Register tag with description
			$this->addTag($tag->getName(), $tag->description);
		}

		// Parse methods
		foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			$operationAttributes = $this->getOperationAttributes($method);

			if ($operationAttributes === []) {
				continue;
			}

			$endpoint = $this->parseMethod(
				$controllerClass,
				$controllerPath,
				$controllerTags,
				$method,
				$operationAttributes,
			);

			if ($endpoint !== null) {
				$endpoints[] = $endpoint;

				// Parse aliases and create duplicate endpoints with different masks
				$aliasEndpoints = $this->parseAliases($method, $endpoint, $controllerPath);
				$endpoints = array_merge($endpoints, $aliasEndpoints);
			}
		}

		return $endpoints;
	}

	/**
	 * Parse Alias attributes and create duplicate endpoints for each alias path.
	 *
	 * @param array<mixed> $originalEndpoint
	 * @return array<array<mixed>>
	 */
	private function parseAliases(ReflectionMethod $method, array $originalEndpoint, string $controllerPath): array
	{
		$aliasEndpoints = [];

		foreach ($method->getAttributes(Alias::class) as $attribute) {
			$alias = $attribute->newInstance();
			assert($alias instanceof Alias);

			// Create a copy of the endpoint with the alias path
			$aliasEndpoint = $originalEndpoint;
			$aliasEndpoint['mask'] = $this->buildMask($controllerPath, $alias->path);

			// Rebuild pattern for the new mask
			$this->rebuildPattern($aliasEndpoint);

			$aliasEndpoints[] = $aliasEndpoint;
		}

		return $aliasEndpoints;
	}

	/**
	 * Rebuild the regex pattern for an endpoint based on its mask.
	 *
	 * @param array<mixed> $endpoint
	 */
	private function rebuildPattern(array &$endpoint): void
	{
		$mask = $endpoint['mask'];
		assert(is_string($mask));

		$pattern = Regex::replaceCallback(
			$mask,
			'#{([a-zA-Z0-9\-_]+)}#U',
			static fn (array $matches): string => sprintf('(?P<%s>[^/]+)', $matches[1]),
		);

		$endpoint['attributes'] = ['pattern' => $pattern];
	}

	/**
	 * @return array<RequestOperationAttribute>
	 */
	private function getOperationAttributes(ReflectionMethod $method): array
	{
		$operations = [];

		foreach ($method->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();

			if ($instance instanceof RequestOperationAttribute) {
				$operations[] = $instance;
			}
		}

		return $operations;
	}

	/**
	 * Check if method has #[Hidden] attribute.
	 */
	private function isHidden(ReflectionMethod $method): bool
	{
		return $method->getAttributes(Hidden::class) !== [];
	}

	/**
	 * @param class-string $controllerClass
	 * @param array<string, mixed> $controllerTags
	 * @param array<RequestOperationAttribute> $operationAttributes
	 * @return array<mixed>|null
	 */
	private function parseMethod(
		string $controllerClass,
		string $controllerPath,
		array $controllerTags,
		ReflectionMethod $method,
		array $operationAttributes,
	): array|null
	{
		// Check if endpoint is hidden from OpenAPI documentation
		if ($this->isHidden($method)) {
			return null;
		}

		// Parse operation attributes
		$parsed = $this->parseOperationAttributes($operationAttributes, $controllerTags);

		if ($parsed->path === '') {
			return null;
		}

		// Parse additional attributes on method
		$this->parseParameterAttributes($method, $parsed->parameters);
		$this->requestBodyBuilder->parseRequestBodyAttribute($method, $parsed->requestBody);
		$this->requestBodyBuilder->parseFileUploadAttributes($method, $parsed->requestBody);
		$this->responseBuilder->parseResponseAttributes($method, $parsed->responses);
		$this->parseTagAttributes($method, $parsed->tags);

		// Infer parameters from method signature (for parameters not explicitly defined)
		$this->parameterEngine->inferFromMethodSignature($method, $parsed->path, $parsed->parameters);

		// Build full mask
		$mask = $this->buildMask($controllerPath, $parsed->path);

		// Build endpoint data
		$endpoint = [
			'attributes' => ['pattern' => null],
			'handler' => [
				'class' => $controllerClass,
				'method' => $method->getName(),
			],
			'id' => $parsed->id,
			'summary' => $parsed->summary,
			'deprecated' => $parsed->deprecated,
			'mask' => $mask,
			'methods' => $parsed->httpMethods,
			'parameters' => [],
			'responses' => $parsed->responses,
			'tags' => $parsed->tags,
		];

		$this->processPatternAndParameters($endpoint, $parsed->parameters);

		if ($parsed->requestBody !== null) {
			$endpoint['requestBody'] = $parsed->requestBody;
		}

		// Parse OpenApiMerge attribute for custom spec
		$mergeSpec = $this->parseOpenApiMergeAttribute($method);

		if ($mergeSpec !== null) {
			$endpoint['openApi'] = $mergeSpec;
		}

		return $endpoint;
	}

	/**
	 * Parse OpenApiMerge attribute for custom OpenAPI spec.
	 *
	 * @return array<string, mixed>|null
	 */
	private function parseOpenApiMergeAttribute(ReflectionMethod $method): array|null
	{
		$attributes = $method->getAttributes(OpenApiMerge::class);

		if ($attributes === []) {
			return null;
		}

		$merge = $attributes[0]->newInstance();
		assert($merge instanceof OpenApiMerge);

		return $merge->spec;
	}

	/**
	 * @param array<RequestOperationAttribute> $operationAttributes
	 * @param array<string, mixed> $controllerTags
	 */
	private function parseOperationAttributes(array $operationAttributes, array $controllerTags): ParsedOperation
	{
		$parsed = new ParsedOperation(tags: $controllerTags);

		foreach ($operationAttributes as $operation) {
			$parsed->httpMethods[] = strtoupper($operation->getMethod());

			if ($operation->getPath() !== null) {
				$parsed->path = $operation->getPath();
			}

			if (!$operation instanceof Operation) {
				continue;
			}

			$this->parseOperation($operation, $parsed);
		}

		return $parsed;
	}

	private function parseOperation(Operation $operation, ParsedOperation $parsed): void
	{
		if ($operation->operationId !== null) {
			$parsed->id = $operation->operationId;
		}

		if ($operation->summary !== null) {
			$parsed->summary = $operation->summary;
		}

		if ($operation->deprecated) {
			$parsed->deprecated = true;
		}
	}

	private function buildMask(string $controllerPath, string $path): string
	{
		$maskParts = array_filter([$controllerPath, $path]);
		$mask = implode('/', $maskParts);

		return '/' . trim((string) preg_replace('#/{2,}#', '/', $mask), '/');
	}

	/**
	 * @param array<mixed> $endpoint
	 * @param array<string, array<mixed>> $definedParameters
	 */
	private function processPatternAndParameters(array &$endpoint, array $definedParameters): void
	{
		$mask = $endpoint['mask'];
		$maskParameters = [];

		/** @var array<string, array<mixed>> $pathParameters */
		$pathParameters = array_filter(
			$definedParameters,
			static fn (array $param): bool => $param['in'] === EndpointParameter::InPath,
		);

		/** @var array<string, array<mixed>> $notPathParameters */
		$notPathParameters = array_filter(
			$definedParameters,
			static fn (array $param): bool => $param['in'] !== EndpointParameter::InPath,
		);

		// Collect variable parameters from URL
		$pattern = Regex::replaceCallback(
			$mask,
			'#{([a-zA-Z0-9\-_]+)}#U',
			static function (array $matches) use (&$endpoint, &$maskParameters): string {
				[, $variableName] = $matches;

				// Duplication check
				if (isset($maskParameters[$variableName])) {
					throw new InvalidStateException(
						sprintf('Duplicate mask parameter "%s" in path "%s"', $variableName, $endpoint['mask']),
					);
				}

				// Build parameter pattern
				$pattern = sprintf('(?P<%s>[^/]+)', $variableName);

				// Build mask parameters
				$maskParameters[$variableName] = [
					'name' => $variableName,
					'pattern' => $pattern,
				];

				return $pattern;
			},
		);

		// Check if @RequestParameter(in=path) is also defined in mask
		foreach ($pathParameters as $name => $parameter) {
			$found = false;

			foreach ($maskParameters as $maskParameter) {
				if ($maskParameter['name'] === $name) {
					$found = true;

					break;
				}
			}

			if (!$found) {
				throw new InvalidStateException(
					sprintf(
						'@RequestParameter(name="%s", in=path) is not defined in mask (@Path annotations)',
						$name,
					),
				);
			}
		}

		// Fulfill endpoint parameters (in path)
		foreach ($maskParameters as $maskParameter) {
			$name = $maskParameter['name'];
			$param = $definedParameters[$name] ?? $this->parameterEngine->getDefaultParameter(
				$name,
				EndpointParameter::InPath,
			);
			$endpoint['parameters'][$name] = $param;
		}

		// Append all other parameters
		foreach ($notPathParameters as $name => $param) {
			$endpoint['parameters'][$name] = $param;
		}

		// Build final regex pattern
		$endpoint['attributes']['pattern'] = $pattern;
	}

	/**
	 * @param array<string, array<mixed>> $parameters
	 */
	private function parseParameterAttributes(ReflectionMethod $method, array &$parameters): void
	{
		foreach ($method->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();

			if ($instance instanceof RequestParameter) {
				$name = $instance->name;

				if ($name === null) {
					continue;
				}

				$parameters[$name] = $this->parameterEngine->buildParameter($instance);
			}
		}
	}

	/**
	 * @param array<string, mixed> $tags
	 */
	private function parseTagAttributes(ReflectionMethod $method, array &$tags): void
	{
		foreach ($method->getAttributes(Tag::class) as $attribute) {
			$tag = $attribute->newInstance();
			assert($tag instanceof Tag);
			$tags[$tag->getName()] = $tag->getValue();
		}
	}

	private function addTag(string $name, string|null $description): void
	{
		if (!isset($this->tags[$name]) || ($description !== null && $this->tags[$name]['description'] === null)) {
			$this->tags[$name] = [
				'name' => $name,
				'description' => $description,
			];
		}
	}

}
