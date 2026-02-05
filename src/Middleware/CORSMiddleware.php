<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function implode;
use function in_array;

/**
 * CORS (Cross-Origin Resource Sharing) middleware.
 *
 * Handles preflight requests and adds appropriate CORS headers to responses.
 *
 * SECURITY: When allowCredentials is true, you MUST specify explicit allowedOrigins.
 * Using credentials with wildcard origin ('*') is a security vulnerability that allows
 * any website to make authenticated requests to your API.
 */
#[MiddlewarePriority(200)]
class CORSMiddleware implements Middleware
{

	/**
	 * Default CORS preflight cache duration (1 hour in seconds).
	 *
	 * Lower default reduces risk during rolling deployments where CORS config changes
	 * wouldn't take effect until browser cache expires.
	 */
	private const DEFAULT_MAX_AGE_SECONDS = 3_600;

	/**
	 * @param list<string> $allowedOrigins List of allowed origins (e.g., ['https://example.com']). Empty = allow all.
	 * @param list<string> $allowedMethods List of allowed HTTP methods (e.g., ['GET', 'POST']).
	 * @param list<string> $allowedHeaders List of allowed headers (e.g., ['Content-Type', 'Authorization']).
	 * @param bool $allowCredentials Whether to allow credentials (cookies, authorization headers).
	 * @param int $maxAge How long preflight results can be cached (in seconds).
	 * @param list<string> $exposedHeaders Headers that can be exposed to the browser.
	 * @throws InvalidArgumentException When allowCredentials is true with wildcard origins
	 */
	public function __construct(
		private array $allowedOrigins = [],
		private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
		private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
		private bool $allowCredentials = false,
		private int $maxAge = self::DEFAULT_MAX_AGE_SECONDS,
		private array $exposedHeaders = [],
	)
	{
		$this->validateConfiguration();
	}

	/**
	 * Validate CORS configuration for security issues.
	 *
	 * @throws InvalidArgumentException When configuration is insecure
	 */
	private function validateConfiguration(): void
	{
		if (!$this->allowCredentials) {
			return;
		}

		// Check for wildcard in allowedOrigins
		if (in_array('*', $this->allowedOrigins, true)) {
			throw new InvalidArgumentException(ErrorMessages::CORS_CREDENTIALS_WILDCARD);
		}

		// Check for empty allowedOrigins (which acts as wildcard)
		if ($this->allowedOrigins === []) {
			throw new InvalidArgumentException(ErrorMessages::CORS_CREDENTIALS_EMPTY);
		}
	}

	private function decorate(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		$origin = $request->getHeaderLine('Origin');

		// Determine allowed origin
		$allowedOrigin = $this->resolveAllowedOrigin($origin);

		if ($allowedOrigin === null) {
			return $response;
		}

		$response = $response
			->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
			->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
			->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));

		if ($this->allowCredentials) {
			$response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
		}

		if ($this->maxAge > 0) {
			$response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
		}

		if ($this->exposedHeaders !== []) {
			$response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
		}

		// Vary header for proper caching when origin validation is used
		if ($this->allowedOrigins !== []) {
			$response = $response->withHeader('Vary', 'Origin');
		}

		return $response;
	}

	private function resolveAllowedOrigin(string $origin): string|null
	{
		// No origin header = not a CORS request
		if ($origin === '') {
			return '*';
		}

		// Empty allowedOrigins = allow all origins (wildcard)
		// Note: This path is only reached when allowCredentials=false
		// (credentials with empty origins is blocked in constructor)
		if ($this->allowedOrigins === []) {
			return '*';
		}

		// Check if origin is in whitelist
		if (in_array($origin, $this->allowedOrigins, true)) {
			return $origin;
		}

		// Origin not allowed
		return null;
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		// Preflight request
		if ($request->getMethod() === 'OPTIONS') {
			return $this->decorate($request, $response)->withStatus(204);
		}

		$response = $next($request, $response);

		return $this->decorate($request, $response);
	}

}
