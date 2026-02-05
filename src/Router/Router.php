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
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtoupper;
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

			if ($cached !== null) {
				$this->loadFromCache($cached);
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
	 * @param array{staticRoutes: array<string, array<string, int>>, dynamicRoutes: array<string, array<array{pattern: string, endpointIndex: int}>>} $cached
	 */
	private function loadFromCache(array $cached): void
	{
		$endpoints = $this->schema->getEndpoints();

		foreach ($cached['staticRoutes'] as $method => $routes) {
			foreach ($routes as $path => $endpointIndex) {
				$this->staticRoutes[$method][$path] = $endpoints[$endpointIndex];
			}
		}

		foreach ($cached['dynamicRoutes'] as $method => $routes) {
			foreach ($routes as $route) {
				$this->dynamicRoutes[$method][] = [
					'pattern' => $route['pattern'],
					'endpoint' => $endpoints[$route['endpointIndex']],
				];
			}
		}
	}

	/**
	 * @return array{staticRoutes: array<string, array<string, int>>, dynamicRoutes: array<string, array<array{pattern: string, endpointIndex: int}>>}
	 */
	private function buildCacheData(): array
	{
		$endpoints = $this->schema->getEndpoints();
		$endpointIndexMap = $this->buildEndpointIndexMap($endpoints);

		$staticRoutes = [];

		foreach ($this->staticRoutes as $method => $routes) {
			foreach ($routes as $path => $endpoint) {
				$key = $this->getEndpointKey($endpoint);
				$staticRoutes[$method][$path] = $endpointIndexMap[$key];
			}
		}

		$dynamicRoutes = [];

		foreach ($this->dynamicRoutes as $method => $routes) {
			foreach ($routes as $route) {
				$key = $this->getEndpointKey($route['endpoint']);
				$dynamicRoutes[$method][] = [
					'pattern' => $route['pattern'],
					'endpointIndex' => $endpointIndexMap[$key],
				];
			}
		}

		return [
			'staticRoutes' => $staticRoutes,
			'dynamicRoutes' => $dynamicRoutes,
		];
	}

	/**
	 * Build a map of endpoint keys to their indices.
	 *
	 * Uses controller class + method as stable identifier (survives between requests).
	 *
	 * @param array<Endpoint> $endpoints
	 * @return array<string, int>
	 */
	private function buildEndpointIndexMap(array $endpoints): array
	{
		$map = [];

		foreach ($endpoints as $index => $endpoint) {
			$key = $this->getEndpointKey($endpoint);
			$map[$key] = $index;
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
			$path = mb_substr($path, mb_strlen($this->basePath));
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

			if (isset($pathParams[$name])) {
				$pathParameters[$name] = $pathParams[$name];
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
