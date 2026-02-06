<?php declare(strict_types = 1);

namespace Sabservis\Api\Exception;

/**
 * Centralized error messages for API exceptions.
 *
 * Contains all user-facing error messages used in ClientErrorException.
 * Internal/runtime exceptions use their own messages.
 */
final class ErrorMessages
{

	// Router
	public const METHOD_NOT_ALLOWED = 'Method "%s" is not allowed for endpoint "%s".';

	public const NOT_FOUND = 'Not found';

	// Rate limiting
	public const TOO_MANY_REQUESTS = 'Too Many Requests. Limit: %d per %ds';

	// Request size
	public const PAYLOAD_TOO_LARGE = 'Payload Too Large. Maximum allowed size: %s';

	public const INVALID_CONTENT_LENGTH = 'Invalid Content-Length header';

	// Content-Type
	public const UNSUPPORTED_CONTENT_TYPE = "Unsupported Content-Type '%s'. Expected: %s";

	// HTTPS
	public const HTTPS_REQUIRED = 'Encrypted connection is required. Please use https connection.';

	// Authorization
	public const FORBIDDEN_ACTIVITY = 'Forbidden. Missing permission for activity "%s".';

	// CORS
	public const CORS_CREDENTIALS_WILDCARD = 'CORS configuration error: allowCredentials cannot be used with wildcard origin "*". This is a security vulnerability. Specify explicit allowed origins instead.';

	public const CORS_CREDENTIALS_EMPTY = 'CORS configuration error: allowCredentials cannot be used with empty allowedOrigins (which allows all origins). Specify explicit allowed origins for security.';

	// Parameters
	public const PARAMETER_REQUIRED = '%s request parameter "%s" should be provided.';

	public const PARAMETER_EMPTY = '%s request parameter "%s" should not be empty.';

	public const PARAMETER_INVALID_TYPE = "Parameter '%s': invalid value '%s'. Expected %s.";

	public const PARAMETER_INVALID_INT = "Parameter '%s': invalid value '%s'. Expected integer.";

	public const PARAMETER_INVALID_FLOAT = "Parameter '%s': invalid value '%s'. Expected number (e.g. 3.14).";

	public const PARAMETER_INVALID_BOOL = "Parameter '%s': invalid value '%s'. Expected boolean (true/false, 1/0, yes/no, on/off).";

	public const PARAMETER_INVALID_DATETIME = "Parameter '%s': invalid value '%s'. Expected date/datetime (e.g. 2024-01-30 or 2024-01-30T15:30:00).";

	public const PARAMETER_INVALID_ENUM = "Parameter '%s': invalid value '%s'. Expected one of: %s.";

	// Files
	public const FILE_REQUIRED = "Required file '%s' is missing";

	public const FILES_REQUIRED = "Required file(s) '%s' missing";

	public const FILE_EMPTY = "File '%s' is empty (0 bytes)";

	public const FILE_INVALID_TYPE = "File '%s' has invalid type '%s'. Allowed types: %s";

	// JSON
	public const JSON_EMPTY_BODY = 'Request body is empty';

	public const JSON_INVALID = 'Invalid JSON: %s';

	public const JSON_NOT_ARRAY = 'JSON must be an object or array';

}
