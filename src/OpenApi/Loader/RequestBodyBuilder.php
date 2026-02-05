<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

use ReflectionAttribute;
use ReflectionMethod;
use Sabservis\Api\Attribute\OpenApi\Encoding;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use function array_map;
use function assert;

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

}
