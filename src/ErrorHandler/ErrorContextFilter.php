<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

/**
 * Filters error context data before it's included in the response.
 *
 * Called AFTER the built-in sensitive key filtering.
 * Register as a service in DI container — the framework autowires it
 * into ErrorResponseBuilder automatically.
 */
interface ErrorContextFilter
{

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function filter(array $context): array;

}
