<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

use ReflectionMethod;
use Sabservis\Api\Attribute\OpenApi\FileResponse as FileResponseAttribute;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\MediaType;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Schema\EndpointResponse;
use function array_merge;
use function array_unique;
use function assert;

/**
 * Builds response specifications from attributes.
 *
 * Handles:
 * - #[Response] attribute parsing
 * - #[FileResponse] attribute parsing
 * - Extracting referenced schema classes
 * - Default response descriptions
 */
final class ResponseBuilder
{

	/**
	 * Default descriptions for HTTP status codes.
	 */
	private const DEFAULT_DESCRIPTIONS = [
		'200' => 'OK',
		'201' => 'Created',
		'204' => 'No Content',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'422' => 'Unprocessable Entity',
		'500' => 'Internal Server Error',
	];

	/**
	 * Parse #[Response] and #[FileResponse] attributes from method.
	 *
	 * @param array<string, array<mixed>> $responses Reference to responses array (modified in place)
	 */
	public function parseResponseAttributes(ReflectionMethod $method, array &$responses): void
	{
		$this->parseJsonResponses($method, $responses);
		$this->parseFileResponses($method, $responses);
	}

	/**
	 * Get default description for HTTP status code.
	 */
	public function getDefaultDescription(string $code): string
	{
		return self::DEFAULT_DESCRIPTIONS[$code] ?? 'Response';
	}

	/**
	 * Parse #[Response] attributes.
	 *
	 * @param array<string, array<mixed>> $responses
	 */
	private function parseJsonResponses(ReflectionMethod $method, array &$responses): void
	{
		foreach ($method->getAttributes(Response::class) as $attribute) {
			$response = $attribute->newInstance();
			assert($response instanceof Response);
			$code = $response->getStatusCode();

			$responseData = [
				'code' => $code,
				'description' => $response->description ?? $this->getDefaultDescription($code),
				'entity' => $response->ref,
			];

			// Handle listRef responses (array of items, optionally with meta or data wrapper)
			if ($response->listRef !== null) {
				$responseData['entity'] = $response->listRef;

				if ($response->withMeta) {
					$responseData['wrapperType'] = EndpointResponse::WrapperListWithMeta;
				} elseif ($response->unwrapped) {
					$responseData['wrapperType'] = EndpointResponse::WrapperListNoMeta;
				} else {
					$responseData['wrapperType'] = EndpointResponse::WrapperListDataOnly;
				}
			}

			// Extract content spec from contentArray (for oneOf, anyOf, custom schemas)
			if ($response->ref === null && $response->listRef === null && $response->contentArray !== null) {
				$spec = $response->toOpenApiSpec();

				if (isset($spec['content'])) {
					$responseData['contentSpec'] = $spec['content'];
				}

				// Extract referenced classes for schema registration
				$referencedClasses = $this->extractReferencedClasses($response->contentArray);

				if ($referencedClasses !== []) {
					$responseData['referencedSchemas'] = $referencedClasses;
				}
			}

			$responses[$code] = $responseData;
		}
	}

	/**
	 * Parse #[FileResponse] attributes.
	 *
	 * @param array<string, array<mixed>> $responses
	 */
	private function parseFileResponses(ReflectionMethod $method, array &$responses): void
	{
		foreach ($method->getAttributes(FileResponseAttribute::class) as $attribute) {
			$fileResponse = $attribute->newInstance();
			assert($fileResponse instanceof FileResponseAttribute);
			$code = $fileResponse->getStatusCode();

			$responses[$code] = [
				'code' => $code,
				'description' => $fileResponse->description ?? 'File download',
				'entity' => null,
				'fileResponse' => [
					'contentType' => $fileResponse->contentType,
					'filename' => $fileResponse->filename,
				],
			];
		}
	}

	/**
	 * Extract all referenced classes from response content array.
	 *
	 * @param array<JsonContent|MediaType> $contentArray
	 * @return array<class-string>
	 */
	private function extractReferencedClasses(array $contentArray): array
	{
		$classes = [];

		foreach ($contentArray as $content) {
			if ($content instanceof JsonContent) {
				$classes = array_merge($classes, $content->getReferencedClasses());
			}
		}

		return array_unique($classes);
	}

}
