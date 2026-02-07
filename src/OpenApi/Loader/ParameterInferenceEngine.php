<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\RequestParameter;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Schema\EndpointParameter;
use UnitEnum;
use function array_flip;
use function assert;
use function class_exists;
use function in_array;
use function is_subclass_of;
use function preg_match_all;
use function strtoupper;

/**
 * Infers API parameters from method signatures and DTO classes.
 *
 * Handles:
 * - Inference from method signature (path vs query parameters)
 * - Extraction of query parameters from DTO ref classes
 * - Building parameter arrays from RequestParameter attributes
 */
final class ParameterInferenceEngine
{

	/**
	 * Infer parameters from method signature.
	 *
	 * This allows controllers to use typed method parameters instead of explicit attributes:
	 *
	 * Before (explicit):
	 *   #[Get('/users/{id}')]
	 *   #[PathParameter(name: 'id', type: 'int')]
	 *   public function get(ApiRequest $request): UserDto
	 *
	 * After (inferred):
	 *   #[Get('/users/{id}')]
	 *   public function get(int $id): UserDto
	 *
	 * Rules:
	 * - ApiRequest and ApiResponse parameters are skipped (injected by ServiceHandler)
	 * - Parameters matching URL path variables (e.g., {id}) become path parameters
	 * - Other parameters become query parameters
	 * - Nullable or optional parameters are not required
	 * - Explicit #[RequestParameter] attributes take precedence
	 *
	 * @param array<string, array<mixed>> $parameters Existing parameters (from explicit attributes)
	 * @param array<string> $httpMethods HTTP methods used by endpoint (for DTO body detection)
	 */
	public function inferFromMethodSignature(
		ReflectionMethod $method,
		string $path,
		array &$parameters,
		string|null $requestBodyEntity = null,
		array $httpMethods = [],
	): void
	{
		$pathVariables = $this->extractPathVariables($path);

		foreach ($method->getParameters() as $param) {
			$paramName = $param->getName();

			// Skip if already defined via explicit attribute
			if (isset($parameters[$paramName])) {
				continue;
			}

			$typeInfo = $this->resolveParameterType($param);

			if ($typeInfo === null) {
				continue;
			}

			$typeName = $typeInfo['name'];
			$allowsNull = $typeInfo['allowsNull'];

			// Skip ApiRequest and ApiResponse - these are injected by ServiceHandler
			if ($typeName === ApiRequest::class || $typeName === ApiResponse::class) {
				continue;
			}

			// DTO from request body should not be inferred as query parameter
			if ($this->isRequestBodyParameter(
				$paramName,
				$typeName,
				$pathVariables,
				$requestBodyEntity,
				$httpMethods,
			)) {
				continue;
			}

			// Determine if this is a path or query parameter
			$isPathParam = isset($pathVariables[$paramName]);
			$in = $isPathParam ? EndpointParameter::InPath : EndpointParameter::InQuery;

			// Path parameters are always required
			// Query parameters are required unless nullable or has default value
			$isRequired = $isPathParam || (!$allowsNull && !$param->isOptional());

			// Map PHP type to our internal type representation
			$type = $this->mapPhpTypeToParameterType($typeName);

			$parameters[$paramName] = [
				'name' => $paramName,
				'type' => $type,
				'in' => $in,
				'required' => $isRequired,
				'description' => null,
				'deprecated' => false,
				'allowEmptyValue' => false,
			];
		}
	}

	/**
	 * Infer request body from method signature for HTTP methods with body support.
	 *
	 * @param array<string, array<mixed>> $parameters Existing explicit parameters (not inferred)
	 * @param array<string> $httpMethods
	 * @return array<string, mixed>|null
	 */
	public function inferRequestBodyFromMethodSignature(
		ReflectionMethod $method,
		string $path,
		array $parameters,
		array $httpMethods,
	): array|null
	{
		if (!$this->allowsRequestBody($httpMethods)) {
			return null;
		}

		$pathVariables = $this->extractPathVariables($path);

		foreach ($method->getParameters() as $param) {
			$paramName = $param->getName();

			// Respect explicit parameter attributes
			if (isset($parameters[$paramName])) {
				continue;
			}

			$typeInfo = $this->resolveParameterType($param);

			if ($typeInfo === null) {
				continue;
			}

			$typeName = $typeInfo['name'];
			$allowsNull = $typeInfo['allowsNull'];

			if ($typeName === ApiRequest::class || $typeName === ApiResponse::class) {
				continue;
			}

			if (isset($pathVariables[$paramName])) {
				continue;
			}

			if (!$this->isDtoClass($typeName)) {
				continue;
			}

			return [
				'description' => '',
				'entity' => $typeName,
				'required' => !$allowsNull && !$param->isOptional(),
				'contentSpec' => null,
			];
		}

		return null;
	}

	/**
	 * Extract query parameters from a DTO class using #[Property] attributes.
	 *
	 * @param class-string $refClass
	 * @return array<string, array<mixed>>
	 */
	public function extractQueryParametersFromRef(string $refClass): array
	{
		if (!class_exists($refClass)) {
			return [];
		}

		$parameters = [];
		$reflectionClass = new ReflectionClass($refClass);

		foreach ($reflectionClass->getProperties() as $property) {
			$propertyAttributes = $property->getAttributes(Property::class);

			if ($propertyAttributes === []) {
				continue;
			}

			$propertyAttr = $propertyAttributes[0]->newInstance();
			assert($propertyAttr instanceof Property);

			$name = $propertyAttr->property ?? $property->getName();

			$type = $propertyAttr->type;

			if ($type === null) {
				$reflectionType = $property->getType();

				if ($reflectionType instanceof ReflectionNamedType) {
					$type = $reflectionType->getName();
				}
			}

			$type ??= 'string';

			$parameters[$name] = [
				'name' => $name,
				'type' => $type,
				'in' => EndpointParameter::InQuery,
				'required' => $propertyAttr->required ?? false,
				'description' => $propertyAttr->description,
				'deprecated' => $propertyAttr->deprecated ?? false,
				'allowEmptyValue' => false,
			];
		}

		return $parameters;
	}

	/**
	 * Build parameter array from RequestParameter attribute.
	 *
	 * @return array<string, mixed>
	 */
	public function buildParameter(RequestParameter $param): array
	{
		// Extract type from schema if provided, otherwise use explicit type
		$type = $param->type;
		$schemaSpec = null;
		$schemaRef = null;

		if ($param->schema !== null) {
			$schemaSpec = $param->schema->toOpenApiSpec();

			// If schema has type, use it (overrides default 'string')
			if (isset($schemaSpec['type'])) {
				$type = $schemaSpec['type'];
			}

			// Preserve original ref for class registration in OpenApiGenerator
			$schemaRef = $param->schema->ref;
		}

		return [
			'name' => $param->name,
			'type' => $type,
			'in' => $param->in,
			'required' => $param->isRequired(),
			'description' => $param->description,
			'deprecated' => $param->deprecated,
			'allowEmptyValue' => $param->allowEmptyValue,
			'example' => $param->example,
			'style' => $param->style,
			'explode' => $param->explode,
			'schemaSpec' => $schemaSpec,
			'schemaRef' => $schemaRef,
		];
	}

	/**
	 * Get default parameter definition for a path variable.
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultParameter(string $name, string $in): array
	{
		return [
			'name' => $name,
			'type' => EndpointParameter::TypeString,
			'in' => $in,
			'required' => $in === EndpointParameter::InPath,
			'description' => null,
			'deprecated' => false,
			'allowEmptyValue' => false,
		];
	}

	/**
	 * Extract path variable names from URL pattern.
	 *
	 * @return array<string, int> Variable names as keys (for isset lookup)
	 */
	private function extractPathVariables(string $path): array
	{
		$pathVariables = [];

		if (preg_match_all('#{([a-zA-Z0-9\-_]+)}#', $path, $matches)) {
			$pathVariables = array_flip($matches[1]);
		}

		return $pathVariables;
	}

	/**
	 * Map PHP type name to parameter type string.
	 */
	private function mapPhpTypeToParameterType(string $phpType): string
	{
		return match ($phpType) {
			'int' => 'int',
			'float' => 'float',
			'bool' => 'bool',
			'string' => 'string',
			'array' => 'array',
			default => $phpType, // class name (DateTimeImmutable, enum, etc.)
		};
	}

	/**
	 * @return array{name: string, allowsNull: bool}|null
	 */
	private function resolveParameterType(ReflectionParameter $parameter): array|null
	{
		$paramType = $parameter->getType();

		if ($paramType === null) {
			return null;
		}

		if ($paramType instanceof ReflectionUnionType) {
			$allowsNull = $paramType->allowsNull();

			foreach ($paramType->getTypes() as $unionType) {
				if ($unionType instanceof ReflectionNamedType && $unionType->getName() !== 'null') {
					return [
						'name' => $unionType->getName(),
						'allowsNull' => $allowsNull,
					];
				}
			}

			return null;
		}

		if (!$paramType instanceof ReflectionNamedType) {
			return null;
		}

		return [
			'name' => $paramType->getName(),
			'allowsNull' => $paramType->allowsNull(),
		];
	}

	/**
	 * @param array<string, int> $pathVariables
	 * @param array<string> $httpMethods
	 */
	private function isRequestBodyParameter(
		string $parameterName,
		string $typeName,
		array $pathVariables,
		string|null $requestBodyEntity,
		array $httpMethods,
	): bool
	{
		if (isset($pathVariables[$parameterName])) {
			return false;
		}

		if ($requestBodyEntity !== null && $typeName === $requestBodyEntity) {
			return true;
		}

		if (!$this->allowsRequestBody($httpMethods)) {
			return false;
		}

		return $this->isDtoClass($typeName);
	}

	/**
	 * @param array<string> $httpMethods
	 */
	private function allowsRequestBody(array $httpMethods): bool
	{
		foreach ($httpMethods as $method) {
			if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
				continue;
			}

			return true;
		}

		return false;
	}

	private function isDtoClass(string $typeName): bool
	{
		if (!class_exists($typeName)) {
			return false;
		}

		if ($this->isSpecialValueObjectType($typeName)) {
			return false;
		}

		$reflection = new ReflectionClass($typeName);

		return !$reflection->isInternal();
	}

	private function isSpecialValueObjectType(string $typeName): bool
	{
		if ($typeName === DateTimeInterface::class || is_subclass_of($typeName, DateTimeInterface::class)) {
			return true;
		}

		return is_subclass_of($typeName, BackedEnum::class) || is_subclass_of($typeName, UnitEnum::class);
	}

}
