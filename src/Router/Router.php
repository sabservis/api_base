<?php declare(strict_types = 1);

namespace Sabservis\Api\Router;

use Psr\SimpleCache\CacheInterface;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Utils\Regex;
use function is_array;
use function is_string;
use function rawurldecode;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;
use function trim;

/**
 * High-performance router with O(1) static route lookup.
 *
 * Separates routes into:
 * - Static routes: Direct hash table lookup (O(1))
 * - Dynamic routes: Indexed by HTTP method for reduced iteration
 */
final class Router
{

	private const CacheKeyPrefix = 'sabservis_api_router_';

	protected string|null $basePath = null;

	private bool $compiled = false;

	/** @var array<string, array<string, Endpoint>> method => [path => Endpoint] */
	private array $staticRoutes = [];

	/** @var array<string, array<array{pattern: string, endpoint: Endpoint}>> method => [{pattern, endpoint}] */
	private array $dynamicRoutes = [];

	public function __construct(
		private Schema $schema,
		private CacheInterface|null $cache = null,
	)
	{
	}

	public function setBasePath(string|null $basePath): void
	{
		$this->basePath = $basePath;
	}

	public function getBasePath(): string|null
	{
		return $this->basePath;
	}

	public function match(ApiRequest $request): ApiRequest|null
	{
		if (!$this->compiled) {
			$this->loadFromCacheOrCompile();
		}

		$method = strtoupper($request->getMethod());
		$path = $this->normalizePath($request->getPath());

		// 1. Try static routes first (O(1) hash lookup)
		if (isset($this->staticRoutes[$method][$path])) {
			$endpoint = $this->staticRoutes[$method][$path];

			return $this->buildMatchedRequest($request, $endpoint, []);
		}

		// 2. Try dynamic routes for this method only
		foreach ($this->dynamicRoutes[$method] ?? [] as $route) {
			$match = Regex::match($path, $route['pattern']);

			if ($match !== null) {
				return $this->buildMatchedRequest($request, $route['endpoint'], $match);
			}
		}

		// 3. Check if path exists with different method (405 Method Not Allowed)
		$mask = $this->findPathWithDifferentMethod($method, $path);

		if ($mask !== null) {
			throw new ClientErrorException(
				sprintf(ErrorMessages::METHOD_NOT_ALLOWED, $method, $mask),
				405,
			);
		}

		return null;
	}

	private function loadFromCacheOrCompile(): void
	{
		$cacheKey = self::CacheKeyPrefix . $this->schema->getHash();

		if ($this->cache !== null) {
			$cached = $this->cache->get($cacheKey);

			if (is_array($cached) && $this->loadFromCache($cached)) {
				$this->compiled = true;

				return;
			}
		}

		$this->compile();

		if ($this->cache !== null) {
			$this->cache->set($cacheKey, $this->buildCacheData());
		}
	}

	/**
	 * Restores routes from cache. Endpoints are referenced by stable key (class::method@mask),
	 * never by position, so reordering controllers/methods cannot route to a wrong handler.
	 *
	 * @param array<mixed> $cached
	 * @return bool false when cache is stale or in unknown format (caller recompiles)
	 */
	private function loadFromCache(array $cached): bool
	{
		$endpointMap = $this->buildEndpointMap($this->schema->getEndpoints());
		$staticRoutes = [];
		$dynamicRoutes = [];

		if (!is_array($cached['staticRoutes'] ?? null) || !is_array($cached['dynamicRoutes'] ?? null)) {
			return false;
		}

		foreach ($cached['staticRoutes'] as $method => $routes) {
			foreach ($routes as $path => $endpointKey) {
				if (!is_string($endpointKey) || !isset($endpointMap[$endpointKey])) {
					return false;
				}

				$staticRoutes[$method][$path] = $endpointMap[$endpointKey];
			}
		}

		foreach ($cached['dynamicRoutes'] as $method => $routes) {
			foreach ($routes as $route) {
				$endpointKey = $route['endpointKey'] ?? null;

				if (!is_string($endpointKey) || !isset($endpointMap[$endpointKey])) {
					return false;
				}

				$dynamicRoutes[$method][] = [
					'pattern' => $route['pattern'],
					'endpoint' => $endpointMap[$endpointKey],
				];
			}
		}

		$this->staticRoutes = $staticRoutes;
		$this->dynamicRoutes = $dynamicRoutes;

		return true;
	}

	/**
	 * @return array{staticRoutes: array<string, array<string, string>>, dynamicRoutes: array<string, array<array{pattern: string, endpointKey: string}>>}
	 */
	private function buildCacheData(): array
	{
		$staticRoutes = [];

		foreach ($this->staticRoutes as $method => $routes) {
			foreach ($routes as $path => $endpoint) {
				$staticRoutes[$method][$path] = $this->getEndpointKey($endpoint);
			}
		}

		$dynamicRoutes = [];

		foreach ($this->dynamicRoutes as $method => $routes) {
			foreach ($routes as $route) {
				$dynamicRoutes[$method][] = [
					'pattern' => $route['pattern'],
					'endpointKey' => $this->getEndpointKey($route['endpoint']),
				];
			}
		}

		return [
			'staticRoutes' => $staticRoutes,
			'dynamicRoutes' => $dynamicRoutes,
		];
	}

	/**
	 * @param array<Endpoint> $endpoints
	 * @return array<string, Endpoint>
	 */
	private function buildEndpointMap(array $endpoints): array
	{
		$map = [];

		foreach ($endpoints as $endpoint) {
			$map[$this->getEndpointKey($endpoint)] = $endpoint;
		}

		return $map;
	}

	/**
	 * Get stable cache key for endpoint.
	 *
	 * Uses controller class + method + mask to ensure uniqueness
	 * (same controller method can have multiple endpoints via Alias).
	 */
	private function getEndpointKey(Endpoint $endpoint): string
	{
		return $endpoint->getControllerClass() . '::' . $endpoint->getControllerMethod() . '@' . $endpoint->getMask();
	}

	private function compile(): void
	{
		foreach ($this->schema->getEndpoints() as $endpoint) {
			$mask = $endpoint->getMask();

			if ($mask === null) {
				continue;
			}

			$normalizedMask = $this->normalizePath($mask);

			foreach ($endpoint->getMethods() as $method) {
				$method = strtoupper($method);

				if ($this->isStaticRoute($mask)) {
					// Static route - direct hash lookup
					$this->staticRoutes[$method][$normalizedMask] = $endpoint;
				} else {
					// Dynamic route - needs regex matching
					$this->dynamicRoutes[$method][] = [
						'pattern' => $endpoint->getPattern(),
						'endpoint' => $endpoint,
					];
				}
			}
		}

		$this->compiled = true;
	}

	private function isStaticRoute(string $mask): bool
	{
		// Routes with parameters use {param} or <param> syntax
		return !str_contains($mask, '{') && !str_contains($mask, '<');
	}

	/**
	 * Find if path exists with a different HTTP method.
	 *
	 * Returns the endpoint mask if found, null otherwise.
	 */
	private function findPathWithDifferentMethod(string $method, string $path): string|null
	{
		// Check static routes
		if ($this->pathExistsInStaticRoutes($method, $path)) {
			return $path;
		}

		// Check dynamic routes
		return $this->findPathInDynamicRoutes($method, $path);
	}

	/**
	 * Check if path exists in static routes with a different method.
	 */
	private function pathExistsInStaticRoutes(string $method, string $path): bool
	{
		foreach ($this->staticRoutes as $m => $routes) {
			if ($m !== $method && isset($routes[$path])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find path in dynamic routes with a different method.
	 *
	 * Returns the endpoint mask if found, null otherwise.
	 */
	private function findPathInDynamicRoutes(string $method, string $path): string|null
	{
		foreach ($this->dynamicRoutes as $m => $routes) {
			if ($m === $method) {
				continue;
			}

			foreach ($routes as $route) {
				$match = Regex::match($path, $route['pattern']);

				if ($match !== null) {
					return $route['endpoint']->getMask();
				}
			}
		}

		return null;
	}

	private function normalizePath(string $path): string
	{
		if ($this->basePath !== null && str_starts_with($path, $this->basePath)) {
			$rest = substr($path, strlen($this->basePath));

			// Only at segment boundary - /api must not turn /apiv2/users into /v2/users
			if ($rest === '' || $rest[0] === '/' || str_ends_with($this->basePath, '/')) {
				$path = $rest;
			}
		}

		// Url has always slash at the beginning and no trailing slash at the end
		return '/' . trim($path, '/');
	}

	/**
	 * @param array<string, string> $pathParams
	 */
	private function buildMatchedRequest(
		ApiRequest $request,
		Endpoint $endpoint,
		array $pathParams,
	): ApiRequest
	{
		$pathParameters = [];
		$queryParameters = [];

		// Fill path parameters with matched variables
		foreach ($endpoint->getParametersByIn(EndpointParameter::InPath) as $param) {
			$name = $param->getName();

			// Decoded after match so %2F cannot split segments; rawurldecode keeps "+" (RFC 3986)
			$group = PathVariable::groupName($name);

			if (isset($pathParams[$group])) {
				$pathParameters[$name] = rawurldecode($pathParams[$group]);
			}
		}

		// Fill query parameters with query params
		$queryParams = $request->getQueryParams();

		foreach ($endpoint->getParametersByIn(EndpointParameter::InQuery) as $param) {
			$name = $param->getName();
			$queryParameters[$name] = $queryParams[$name] ?? null;
		}

		// Combined parameters for backward compatibility (path takes precedence)
		$combinedParameters = $queryParameters + $pathParameters;

		// Set attributes to request
		return $request
			->withAttribute(RequestAttributes::Router->value, $pathParams)
			->withAttribute(RequestAttributes::PathParameters->value, $pathParameters)
			->withAttribute(RequestAttributes::QueryParameters->value, $queryParameters)
			->withAttribute(RequestAttributes::Parameters->value, $combinedParameters)
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
	}

}
