<?php declare(strict_types = 1);

namespace Sabservis\Api\Router;

use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Utils\Regex;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_starts_with;
use function trim;

class SimpleRouter implements Router
{

	protected string|null $basePath;

	public function __construct(private Schema $schema)
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
		$endpoints = $this->schema->getEndpoints();
		$exception = null;
		$matched = null;

		// Iterate over all endpoints
		foreach ($endpoints as $endpoint) {
			try {
				$matched = $this->matchEndpoint($endpoint, $request);
			} catch (ClientErrorException $exception) {
				// Don't throw exception unless we know there is no endpoint with same mask which support requested http method
			}

			// Skip if endpoint is not matched
			if ($matched === null) {
				continue;
			}

			// If matched is not null, returns given ServerRequestInterface
			// with all parsed arguments and data,
			// also append given Endpoint
			$matched = $matched
				->withAttribute(RequestAttributes::Endpoint, $endpoint);

			return $matched;
		}

		if ($exception !== null) {
			throw $exception;
		}

		return null;
	}

	protected function matchEndpoint(
		Endpoint $endpoint,
		ApiRequest $request,
	): ApiRequest|null
	{
		// Try match given URL (path) by build pattern
		$request = $this->compareUrl($endpoint, $request);

		// Skip unsupported HTTP method
		if ($request !== null && !$endpoint->hasMethod($request->getMethod())) {
			throw new ClientErrorException(
				sprintf('Method "%s" is not allowed for endpoint "%s".', $request->getMethod(), $endpoint->getMask()),
				405,
			);
		}

		return $request;
	}

	protected function compareUrl(
		Endpoint $endpoint,
		ApiRequest $request,
	): ApiRequest|null
	{
		// Parse url from request
		$url = $request->getUri()->getPath();

		if ($this->basePath !== null) {
			$url = str_starts_with($url, $this->basePath) ? mb_substr($url, mb_strlen($this->basePath)) : $url;
		}

		// Url has always slash at the beginning
		// and no trailing slash at the end
		$url = '/' . trim($url, '/');

		// Try to match against the pattern
		$match = Regex::match($url, $endpoint->getPattern());

		// Skip if there's no match
		if ($match === null) {
			return null;
		}

		$parameters = [];

		// Fill path parameters with matched variables
		foreach ($endpoint->getParametersByIn(EndpointParameter::InPath) as $param) {
			$name = $param->getName();
			$parameters[$name] = $match[$name];
		}

		// Fill query parameters with query params
		$queryParams = $request->getQueryParams();

		foreach ($endpoint->getParametersByIn(EndpointParameter::InQuery) as $param) {
			$name = $param->getName();
			$parameters[$name] = $queryParams[$name] ?? null;
		}

		// Set attributes to request
		$request = $request
			->withAttribute(RequestAttributes::Router, $match)
			->withAttribute(RequestAttributes::Parameters, $parameters);

		return $request;
	}

}
