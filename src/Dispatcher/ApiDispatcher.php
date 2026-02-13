<?php declare(strict_types = 1);

namespace Sabservis\Api\Dispatcher;

use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Exception\Runtime\EarlyReturnResponseException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\FileResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\MultipartEntityHydrator;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Security\AuthorizationChecker;
use function assert;
use function explode;
use function implode;
use function in_array;
use function sprintf;
use function trim;

/**
 * Main API dispatcher - handles routing, parameter mapping, serialization.
 *
 * Exception handling is delegated to ApiMiddleware which wraps this dispatcher.
 * This keeps the dispatcher focused on the happy path.
 */
final class ApiDispatcher
{

	public function __construct(
		private readonly Router $router,
		private readonly ServiceHandler $handler,
		private readonly EntitySerializer $serializer,
		private readonly RequestParameterMapping $parameterMapping,
		private readonly EntityValidator|null $validator = null,
		private readonly AuthorizationChecker|null $authorizationChecker = null,
		private readonly MultipartEntityHydrator|null $multipartHydrator = null,
	)
	{
	}

	public function dispatch(
		ApiRequest $request,
		ApiResponse $response,
	): ApiResponse
	{
		// 1. Match route
		$matchedRequest = $this->router->match($request);

		if ($matchedRequest === null) {
			throw new ClientErrorException(ErrorMessages::NOT_FOUND, 404);
		}

		// 2. Map parameters (path, query, header, cookie)
		$matchedRequest = $this->parameterMapping->map($matchedRequest);

		// 3. Runtime authorization checks
		$this->authorize($matchedRequest);

		// 4. Validate file uploads (MIME types, required files)
		$this->validateFileUploads($matchedRequest);

		// 5. Transform request body to DTO
		$matchedRequest = $this->transformRequest($matchedRequest);

		// 6. Execute handler and transform response
		try {
			$result = $this->handler->handle($matchedRequest, $response);

			return $this->transformResult($result, $response);
		} catch (EarlyReturnResponseException $e) {
			// EarlyReturnResponseException is flow control, not an error
			// Controller wants to return a specific response and skip serialization
			return $e->getResponse();
		}
	}

	private function authorize(ApiRequest $request): void
	{
		if ($this->authorizationChecker === null) {
			return;
		}

		$endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
		assert($endpoint instanceof Endpoint);

		if (!$endpoint->hasAuthorizations()) {
			return;
		}

		$this->authorizationChecker->authorize($request, $endpoint);
	}

	/**
	 * Transform handler result to ApiResponse.
	 */
	private function transformResult(mixed $result, ApiResponse $response): ApiResponse
	{
		// FileResponse - convert to ApiResponse
		if ($result instanceof FileResponse) {
			return $result->toApiResponse();
		}

		// ApiResponse with object - serialize the object
		// Use $result as base to preserve headers set by controller (e.g., Location)
		if ($result instanceof ApiResponse && $result->getObject() !== null) {
			return $this->transformResponse($result->getObject(), $result, $result->getStatusCode());
		}

		// ApiResponse without object - return as-is
		if ($result instanceof ApiResponse) {
			return $result;
		}

		// DTO or other data - serialize to JSON
		return $this->transformResponse($result, $response);
	}

	/**
	 * Transform incoming request body to DTO if endpoint defines request body.
	 */
	private function transformRequest(ApiRequest $request): ApiRequest
	{
		$endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
		assert($endpoint instanceof Endpoint);

		$requestBody = $endpoint->getRequestBody();

		if ($requestBody?->getEntity() === null) {
			return $request;
		}

		// Multipart DTO: hydrate from form data + uploaded files
		if ($requestBody->hasFileUploads() && $this->multipartHydrator !== null) {
			/** @var class-string $entityClass */
			$entityClass = $requestBody->getEntity();
			$dto = $this->multipartHydrator->hydrate($request, $entityClass);

			if ($this->validator !== null) {
				$this->validator->validate($dto);
			}

			return $request
				->withParsedBody($dto)
				->withAttribute(RequestAttributes::RequestEntity->value, $dto);
		}

		$body = $request->getContents();

		// Optional request body: skip deserialization when body is empty.
		// This allows signatures like ?Dto $input = null.
		if ($body === '') {
			if ($requestBody->isRequired()) {
				throw new ClientErrorException(ErrorMessages::JSON_EMPTY_BODY, 400);
			}

			return $request;
		}

		// Validate Content-Type before attempting deserialization
		$this->validateContentType($request, $requestBody->getAllowedContentTypes());

		$dto = $this->serializer->deserialize(
			$body,
			$requestBody->getEntity(),
		);

		if ($this->validator !== null) {
			$this->validator->validate($dto);
		}

		return $request
			->withParsedBody($dto)
			->withAttribute(RequestAttributes::RequestEntity->value, $dto);
	}

	/**
	 * Transform response data to JSON.
	 */
	private function transformResponse(mixed $data, ApiResponse $response, int $statusCode = 200): ApiResponse
	{
		return $response
			->withStatus($statusCode)
			->withHeader('Content-Type', 'application/json')
			// SECURITY: Prevent MIME sniffing attacks
			->withHeader('X-Content-Type-Options', 'nosniff')
			->writeBody($this->serializer->serialize($data));
	}

	/**
	 * Validate file uploads against endpoint's FileUpload definitions.
	 *
	 * Checks:
	 * - Required files are present
	 * - MIME types match allowedTypes (using server-side detection, not client header)
	 *
	 * @throws ClientErrorException When validation fails (400 for missing, 415 for wrong type)
	 */
	private function validateFileUploads(ApiRequest $request): void
	{
		$endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
		assert($endpoint instanceof Endpoint);

		$requestBody = $endpoint->getRequestBody();

		if ($requestBody === null || !$requestBody->hasFileUploads()) {
			return;
		}

		foreach ($requestBody->getFileUploads() as $fileUpload) {
			$this->validateSingleFileUpload($request, $fileUpload);
		}
	}

	/**
	 * Validate a single file upload field.
	 */
	private function validateSingleFileUpload(ApiRequest $request, FileUpload $fileUpload): void
	{
		$fieldName = $fileUpload->name;

		if ($fileUpload->multiple) {
			$files = $request->getUploadedFiles($fieldName);
			$this->validateMultipleFiles($files, $fileUpload);
		} else {
			$file = $request->getUploadedFile($fieldName);
			$this->validateSingleFile($file, $fileUpload);
		}
	}

	/**
	 * Validate a single file.
	 */
	private function validateSingleFile(UploadedFile|null $file, FileUpload $fileUpload): void
	{
		// Check required
		if ($file === null || !$file->isOk()) {
			if ($fileUpload->required) {
				throw new ClientErrorException(
					sprintf(ErrorMessages::FILE_REQUIRED, $fileUpload->name),
					400,
				);
			}

			return; // Optional file not provided - OK
		}

		// SECURITY: Reject 0-byte files - they have no content for MIME detection
		// and are effectively useless (likely failed upload or attack attempt)
		if ($file->getSize() === 0) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::FILE_EMPTY, $fileUpload->name),
				400,
			);
		}

		// Validate MIME type using server-side detection
		$this->validateFileMimeType($file, $fileUpload);
	}

	/**
	 * Validate multiple files.
	 *
	 * @param array<UploadedFile> $files
	 */
	private function validateMultipleFiles(array $files, FileUpload $fileUpload): void
	{
		// Check required
		if ($files === []) {
			if ($fileUpload->required) {
				throw new ClientErrorException(
					sprintf(ErrorMessages::FILES_REQUIRED, $fileUpload->name),
					400,
				);
			}

			return; // Optional files not provided - OK
		}

		// Validate each file
		foreach ($files as $file) {
			if (!$file->isOk()) {
				continue;
			}

			// SECURITY: Reject 0-byte files
			if ($file->getSize() === 0) {
				throw new ClientErrorException(
					sprintf(ErrorMessages::FILE_EMPTY, $fileUpload->name),
					400,
				);
			}

			$this->validateFileMimeType($file, $fileUpload);
		}
	}

	/**
	 * Validate file's MIME type against allowed types.
	 *
	 * Uses server-side content detection (finfo), not client-provided Content-Type.
	 *
	 * @throws ClientErrorException When MIME type is not allowed (415 Unsupported Media Type)
	 */
	private function validateFileMimeType(UploadedFile $file, FileUpload $fileUpload): void
	{
		$allowedTypes = $fileUpload->allowedTypes;

		if ($allowedTypes === null || $allowedTypes === []) {
			return; // No type restrictions
		}

		$actualType = $file->getValidatedContentType();

		if (!$file->isAllowedType($allowedTypes)) {
			throw new ClientErrorException(
				sprintf(
					ErrorMessages::FILE_INVALID_TYPE,
					$fileUpload->name,
					$actualType,
					implode(', ', $allowedTypes),
				),
				415, // Unsupported Media Type
			);
		}
	}

	/**
	 * Validate request Content-Type against allowed types.
	 *
	 * Handles Content-Type with parameters (e.g., "application/json; charset=utf-8")
	 * by comparing only the media type portion.
	 *
	 * @param array<string>|null $allowedTypes List of allowed media types, null means no validation
	 * @throws ClientErrorException When Content-Type is not allowed (415 Unsupported Media Type)
	 */
	private function validateContentType(ApiRequest $request, array|null $allowedTypes): void
	{
		if ($allowedTypes === null || $allowedTypes === []) {
			return; // No validation required
		}

		$contentType = $request->getHeader('content-type');

		// Extract media type without parameters (e.g., "application/json" from "application/json; charset=utf-8")
		$mediaType = $contentType !== null
			? trim(explode(';', $contentType)[0])
			: '';

		if (!in_array($mediaType, $allowedTypes, true)) {
			throw new ClientErrorException(
				sprintf(
					ErrorMessages::UNSUPPORTED_CONTENT_TYPE,
					$contentType ?? '(none)',
					implode(', ', $allowedTypes),
				),
				415,
			);
		}
	}

}
