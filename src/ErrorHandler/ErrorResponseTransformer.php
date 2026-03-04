<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

use Throwable;

/**
 * Transforms the complete error response data before JSON serialization.
 *
 * Called as the last step — after traceId, context filtering, and sanitization.
 * Use for adding custom fields, restructuring format, or project-specific metadata.
 *
 * Register as a service in DI container — the framework autowires it
 * into ErrorResponseBuilder automatically.
 */
interface ErrorResponseTransformer
{

	/**
	 * @param array<string, mixed> $data Response data (code, message, traceId?, context?)
	 * @return array<string, mixed>
	 */
	public function transform(array $data, Throwable $error): array;

}
