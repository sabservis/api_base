<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Sabservis\Api\Attribute\OpenApi\Encoding;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use function array_map;
use function assert;
use function class_exists;

/**
 * Builds request body specifications from attributes.
 *
 * Handles:
 * - #[RequestBody] attribute parsing
 * - #[FileUpload] attribute parsing for multipart/form-data
 * - Building OpenAPI-compatible request body specs
 */
final class RequestBodyBuilder
{

	/**
	 * Parse #[RequestBody] attribute from method and update requestBody reference.
	 *
	 * @param array<mixed>|null $requestBody Reference to request body spec (modified in place)
	 */
	public function parseRequestBodyAttribute(ReflectionMethod $method, array|null &$requestBody): void
	{
		if ($requestBody !== null) {
			return;
		}

		$attributes = $method->getAttributes(RequestBody::class);

		if ($attributes === []) {
			return;
		}

		$requestBodyAttr = $attributes[0]->newInstance();
		assert($requestBodyAttr instanceof RequestBody);
		$requestBody = $this->buildRequestBody($requestBodyAttr);
	}

	/**
	 * Parse #[FileUpload] attributes and build multipart/form-data request body.
	 *
	 * @param array<mixed>|null $requestBody Reference to request body spec (modified in place)
	 */
	public function parseFileUploadAttributes(ReflectionMethod $method, array|null &$requestBody): void
	{
		$fileUploads = $method->getAttributes(FileUpload::class);

		if ($fileUploads === []) {
			return;
		}

		$properties = [];
		$required = [];

		foreach ($fileUploads as $attribute) {
			$fileUpload = $attribute->newInstance();
			assert($fileUpload instanceof FileUpload);

			$property = [
				'type' => 'string',
				'format' => 'binary',
			];

			if ($fileUpload->description !== null) {
				$property['description'] = $fileUpload->description;
			}

			if ($fileUpload->multiple) {
				$properties[$fileUpload->name] = [
					'type' => 'array',
					'items' => $property,
				];

				if ($fileUpload->description !== null) {
					$properties[$fileUpload->name]['description'] = $fileUpload->description;
				}
			} else {
				$properties[$fileUpload->name] = $property;
			}

			if ($fileUpload->required) {
				$required[] = $fileUpload->name;
			}
		}

		$schema = [
			'type' => 'object',
			'properties' => $properties,
		];

		if ($required !== []) {
			$schema['required'] = $required;
		}

		// Parse encoding attributes
		$encoding = $this->parseEncodingAttributes($method);

		$multipartContent = [
			'schema' => $schema,
		];

		if ($encoding !== []) {
			$multipartContent['encoding'] = $encoding;
		}

		$requestBody = [
			'description' => 'File upload',
			'entity' => null,
			'required' => $required !== [],
			'contentSpec' => [
				'multipart/form-data' => $multipartContent,
			],
			'fileUploads' => array_map(
				static fn (ReflectionAttribute $attr): FileUpload => $attr->newInstance(),
				$fileUploads,
			),
		];
	}

	/**
	 * Parse #[Encoding] attributes from method.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function parseEncodingAttributes(ReflectionMethod $method): array
	{
		$encodingAttributes = $method->getAttributes(Encoding::class);
		$encoding = [];

		foreach ($encodingAttributes as $attribute) {
			$encodingAttr = $attribute->newInstance();
			assert($encodingAttr instanceof Encoding);

			$spec = $encodingAttr->toOpenApiSpec();

			if ($spec !== []) {
				$encoding[$encodingAttr->getProperty()] = $spec;
			}
		}

		return $encoding;
	}

	/**
	 * Build request body spec from RequestBody attribute.
	 *
	 * @return array<mixed>
	 */
	public function buildRequestBody(RequestBody $requestBody): array
	{
		$entity = $requestBody->ref;

		// If no direct ref, check if JsonContent has a ref
		if ($entity === null && $requestBody->contentArray !== null) {
			foreach ($requestBody->contentArray as $content) {
				if ($content instanceof JsonContent && $content->ref !== null) {
					$entity = $content->ref;

					break;
				}
			}
		}

		$contentSpec = null;

		if ($entity === null && $requestBody->contentArray !== null) {
			$spec = $requestBody->toOpenApiSpec();

			if (isset($spec['content'])) {
				$contentSpec = $spec['content'];
			}
		}

		return [
			'description' => $requestBody->description ?? '',
			'entity' => $entity,
			'required' => $requestBody->required ?? false,
			'contentSpec' => $contentSpec,
		];
	}

	/**
	 * Check if a DTO class has any properties with #[FileUpload] attribute.
	 *
	 * @param class-string $className
	 */
	public function hasFileUploadProperties(string $className): bool
	{
		$refClass = new ReflectionClass($className);

		foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$fileUploadAttrs = $property->getAttributes(FileUpload::class);

			if ($fileUploadAttrs !== []) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build multipart/form-data request body spec from a DTO class.
	 *
	 * Inspects public properties: those with #[FileUpload] become binary fields,
	 * others become regular form fields with PHP-to-OpenAPI type mapping.
	 *
	 * @param class-string $className
	 * @return array<mixed>
	 */
	public function buildMultipartRequestBody(string $className): array
	{
		$refClass = new ReflectionClass($className);
		$properties = [];
		$required = [];
		$fileUploads = [];

		foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $refProperty) {
			$fileUploadAttrs = $refProperty->getAttributes(FileUpload::class);

			if ($fileUploadAttrs !== []) {
				$this->processFileUploadProperty(
					$refProperty,
					$fileUploadAttrs[0]->newInstance(),
					$properties,
					$required,
					$fileUploads,
				);
			} else {
				$this->processRegularProperty($refProperty, $properties, $required);
			}
		}

		$schema = [
			'type' => 'object',
			'properties' => $properties,
		];

		if ($required !== []) {
			$schema['required'] = $required;
		}

		return [
			'description' => 'Multipart form data',
			'entity' => $className,
			'required' => $required !== [],
			'contentSpec' => [
				'multipart/form-data' => [
					'schema' => $schema,
				],
			],
			'fileUploads' => $fileUploads,
		];
	}

	/**
	 * Process a property with #[FileUpload] attribute into the multipart schema.
	 *
	 * @param array<string, array<string, mixed>> $properties Schema properties (modified in place)
	 * @param array<string> $required Required field names (modified in place)
	 * @param array<FileUpload> $fileUploads Collected FileUpload instances (modified in place)
	 */
	private function processFileUploadProperty(
		ReflectionProperty $refProperty,
		FileUpload $fileUpload,
		array &$properties,
		array &$required,
		array &$fileUploads,
	): void
	{
		$fieldName = $fileUpload->name !== '' ? $fileUpload->name : $refProperty->getName();
		$isRequired = !$this->isNullableProperty($refProperty);

		$binarySpec = [
			'type' => 'string',
			'format' => 'binary',
		];

		if ($fileUpload->description !== null) {
			$binarySpec['description'] = $fileUpload->description;
		}

		if ($fileUpload->multiple) {
			$property = [
				'type' => 'array',
				'items' => $binarySpec,
			];

			if ($fileUpload->description !== null) {
				$property['description'] = $fileUpload->description;
			}
		} else {
			$property = $binarySpec;
		}

		$properties[$fieldName] = $property;

		if ($isRequired) {
			$required[] = $fieldName;
		}

		// Create resolved FileUpload instance for downstream consumers
		$fileUploads[] = new FileUpload(
			name: $fieldName,
			multiple: $fileUpload->multiple,
			required: $isRequired,
			description: $fileUpload->description,
			allowedTypes: $fileUpload->allowedTypes,
		);
	}

	/**
	 * Process a regular (non-file) property into the multipart schema.
	 *
	 * @param array<string, array<string, mixed>> $properties Schema properties (modified in place)
	 * @param array<string> $required Required field names (modified in place)
	 */
	private function processRegularProperty(
		ReflectionProperty $refProperty,
		array &$properties,
		array &$required,
	): void
	{
		$fieldName = $refProperty->getName();
		$isRequired = !$this->isNullableProperty($refProperty);

		$property = $this->mapPropertyToOpenApiType($refProperty);

		// Preserve description from #[Property] attribute if present
		$propertyAttrs = $refProperty->getAttributes(Property::class);

		if ($propertyAttrs !== []) {
			$propertyAttr = $propertyAttrs[0]->newInstance();
			assert($propertyAttr instanceof Property);

			if ($propertyAttr->description !== null) {
				$property['description'] = $propertyAttr->description;
			}
		}

		$properties[$fieldName] = $property;

		if ($isRequired) {
			$required[] = $fieldName;
		}
	}

	/**
	 * Map a PHP property type to an OpenAPI type spec.
	 *
	 * @return array<string, mixed>
	 */
	private function mapPropertyToOpenApiType(ReflectionProperty $refProperty): array
	{
		$type = $refProperty->getType();

		if (!$type instanceof ReflectionNamedType) {
			return ['type' => 'string'];
		}

		$typeName = $type->getName();

		// If it's a class (not a built-in type), store _classRef for later resolution
		if (!$type->isBuiltin() && class_exists($typeName)) {
			return ['_classRef' => $typeName];
		}

		return match ($typeName) {
			'int' => ['type' => 'integer'],
			'float' => ['type' => 'number'],
			'bool' => ['type' => 'boolean'],
			default => ['type' => 'string'],
		};
	}

	/**
	 * Check if a property has a nullable type.
	 */
	private function isNullableProperty(ReflectionProperty $refProperty): bool
	{
		$type = $refProperty->getType();

		if ($type === null) {
			return true;
		}

		return $type->allowsNull();
	}

}
