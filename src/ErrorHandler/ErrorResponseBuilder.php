<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\ApiException;
use Sabservis\Api\Http\ApiResponse;
use Throwable;
use function array_map;
use function get_debug_type;
use function is_array;
use function is_object;
use function is_scalar;
use function preg_match;
use function strtolower;

/**
 * Builds consistent JSON error responses.
 *
 * Used by both ApiDispatcher and ErrorHandler to ensure
 * consistent error format across the application.
 *
 * Response format:
 * {
 *   "code": int,
 *   "message": string,
 *   "context": object|array (optional in production)
 * }
 *
 * SECURITY: Context data is automatically sanitized to prevent accidental
 * exposure of sensitive information. Keys matching common sensitive patterns
 * (password, token, secret, etc.) are removed by default.
 *
 * Custom filtering can be configured via setContextFilter().
 */
final class ErrorResponseBuilder
{

	/**
	 * Patterns for detecting sensitive context keys.
	 * Keys matching these patterns (case-insensitive) are automatically removed.
	 */
	private const SENSITIVE_KEY_PATTERNS = [
		'/password/i',
		'/passwd/i',
		'/secret/i',
		'/token/i',
		'/api[_-]?key/i',
		'/auth/i',
		'/credential/i',
		'/private[_-]?key/i',
		'/access[_-]?key/i',
		'/session[_-]?id/i',
		'/cookie/i',
		'/bearer/i',
		'/hash/i',
		'/salt/i',
		'/cipher/i',
		'/encrypt/i',
		'/card[_-]?number/i',
		'/cvv/i',
		'/pin/i',
		'/ssn/i',
	];

	/**
	 * Custom context filter callback.
	 * Signature: fn(array $context): array
	 *
	 * @var callable|null
	 */
	private $contextFilter = null;

	/**
	 * Whether to include context in responses at all.
	 */
	private bool $includeContext = true;

	/**
	 * Set custom context filter.
	 *
	 * The filter receives the context array and should return a filtered array.
	 * This is called AFTER the default sensitive key filtering.
	 *
	 * @param callable(array<string, mixed>): array<string, mixed> $filter
	 */
	public function setContextFilter(callable $filter): self
	{
		$this->contextFilter = $filter;

		return $this;
	}

	/**
	 * Disable context in error responses entirely.
	 *
	 * When disabled, no context data is ever returned to clients,
	 * regardless of debug mode or exception type.
	 */
	public function disableContext(): self
	{
		$this->includeContext = false;

		return $this;
	}

	/**
	 * Enable context in error responses (default).
	 */
	public function enableContext(): self
	{
		$this->includeContext = true;

		return $this;
	}

	/**
	 * Build error response from throwable.
	 *
	 * @param bool $includeTrace Include stack trace in context (debug mode only)
	 */
	public function build(Throwable $error, bool $includeTrace = false): ApiResponse
	{
		$code = $this->resolveStatusCode($error);
		$data = $this->buildData($error, $includeTrace);

		return (new ApiResponse())
			->withStatus($code)
			->writeJsonBody($data);
	}

	/**
	 * Build minimal error response for fatal errors.
	 */
	public function buildFatal(Throwable $error, bool $debugMode = false): ApiResponse
	{
		$data = $debugMode
			? [
				'code' => 500,
				'message' => 'Fatal error: ' . $error->getMessage(),
				'context' => ['file' => $error->getFile(), 'line' => $error->getLine()],
			]
			: [
				'code' => 500,
				'message' => ServerErrorException::$defaultMessage,
				'context' => [],
			];

		return (new ApiResponse())
			->withStatus(500)
			->writeJsonBody($data);
	}

	/**
	 * @return array{code: int, message: string, context?: mixed}
	 */
	private function buildData(Throwable $error, bool $includeTrace): array
	{
		$isApiException = $error instanceof ApiException;

		// In debug mode, always show real message
		$message = $includeTrace || $isApiException
			? $error->getMessage()
			: ServerErrorException::$defaultMessage;

		$data = [
			'code' => $this->resolveStatusCode($error),
			'message' => $message,
		];

		// Skip context entirely if disabled
		if (!$this->includeContext) {
			return $data;
		}

		// Determine context
		if ($isApiException && ($context = $error->getContext()) !== null) {
			// ApiException has explicit context - sanitize and use it
			$data['context'] = $this->sanitizeContext($context);
		} elseif ($includeTrace) {
			// Debug mode without ApiException context - show sanitized stack trace
			// SECURITY: Never include function arguments - they may contain passwords, tokens, etc.
			$data['context'] = $this->sanitizeTrace($error->getTrace());
		}

		return $data;
	}

	/**
	 * Sanitize context data by removing sensitive keys.
	 *
	 * Applies:
	 * 1. Default sensitive key pattern filtering
	 * 2. Custom filter callback (if configured)
	 * 3. Recursive sanitization of nested arrays
	 */
	private function sanitizeContext(mixed $context): mixed
	{
		// Non-array context: check if it's a sensitive scalar or convert objects
		if (!is_array($context)) {
			// Objects are converted to class name for safety
			if (is_object($context)) {
				return '[object ' . $context::class . ']';
			}

			return $context;
		}

		// Filter out sensitive keys
		$filtered = [];

		foreach ($context as $key => $value) {
			// Check if key matches any sensitive pattern
			if ($this->isSensitiveKey((string) $key)) {
				continue; // Skip sensitive keys
			}

			// Recursively sanitize nested arrays
			if (is_array($value)) {
				$filtered[$key] = $this->sanitizeContext($value);
			} elseif (is_object($value)) {
				// Convert objects to safe representation
				$filtered[$key] = '[object ' . $value::class . ']';
			} elseif (is_scalar($value) || $value === null) {
				$filtered[$key] = $value;
			} else {
				// Resources, closures, etc.
				$filtered[$key] = '[' . get_debug_type($value) . ']';
			}
		}

		// Apply custom filter if configured
		if ($this->contextFilter !== null) {
			$filtered = ($this->contextFilter)($filtered);
		}

		return $filtered;
	}

	/**
	 * Check if a key matches sensitive patterns.
	 */
	private function isSensitiveKey(string $key): bool
	{
		$lowerKey = strtolower($key);

		foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
			if (preg_match($pattern, $lowerKey) === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize stack trace by removing function arguments.
	 *
	 * Function arguments may contain sensitive data (passwords, tokens, API keys)
	 * that should never be exposed in error responses.
	 *
	 * @param array<int, array<string, mixed>> $trace
	 * @return array<int, array{file?: string, line?: int, function?: string, class?: string, type?: string}>
	 */
	private function sanitizeTrace(array $trace): array
	{
		return array_map(static fn (array $frame): array => [
			'file' => $frame['file'] ?? null,
			'line' => $frame['line'] ?? null,
			'function' => $frame['function'] ?? null,
			'class' => $frame['class'] ?? null,
			'type' => $frame['type'] ?? null,
		], $trace);
	}

	private function resolveStatusCode(Throwable $error): int
	{
		if ($error instanceof ApiException) {
			$code = $error->getCode();

			return $code >= 400 && $code < 600 ? $code : 500;
		}

		return 500;
	}

}
