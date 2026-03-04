<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

/**
 * Provides a trace ID for error responses (e.g. request correlation ID).
 *
 * Register as a service in DI container — the framework autowires it
 * into ErrorResponseBuilder automatically.
 */
interface TraceIdProvider
{

	public function get(): string|null;

}
