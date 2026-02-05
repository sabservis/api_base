<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

/**
 * Endpoint facade - unified access to route, handler, and OpenAPI definitions.
 *
 * This class maintains backward compatibility while internally using
 * separate definition classes for better separation of concerns:
 * - RouteDefinition: routing data (methods, mask, pattern)
 * - HandlerDefinition: handler data (controller, method, parameters, requestBody)
 * - OpenApiDefinition: documentation data (responses, tags, openApi)
 */
class Endpoint
{

	public const METHODS = RouteDefinition::METHODS;

	public const TAG_ID = 'id';

	private RouteDefinition $route;

	private HandlerDefinition $handler;

	private OpenApiDefinition $openApi;

	/**
	 * @param class-string $controllerClass
	 */
	public function __construct(
		string $controllerClass,
		string $controllerMethod,
	)
	{
		$this->route = new RouteDefinition();
		$this->handler = new HandlerDefinition($controllerClass, $controllerMethod);
		$this->openApi = new OpenApiDefinition();
	}

	// === Internal access to definitions ===

	public function getRouteDefinition(): RouteDefinition
	{
		return $this->route;
	}

	public function getHandlerDefinition(): HandlerDefinition
	{
		return $this->handler;
	}

	public function getOpenApiDefinition(): OpenApiDefinition
	{
		return $this->openApi;
	}

	// === Handler (delegated to HandlerDefinition) ===

	/**
	 * @return class-string
	 */
	public function getControllerClass(): string
	{
		return $this->handler->getControllerClass();
	}

	public function getControllerMethod(): string
	{
		return $this->handler->getControllerMethod();
	}

	// === Routing (delegated to RouteDefinition) ===

	/**
	 * @return array<string>
	 */
	public function getMethods(): array
	{
		return $this->route->getMethods();
	}

	/**
	 * @param array<string> $methods
	 */
	public function setMethods(array $methods): void
	{
		$this->route->setMethods($methods);
	}

	public function addMethod(string $method): void
	{
		$this->route->addMethod($method);
	}

	public function hasMethod(string $method): bool
	{
		return $this->route->hasMethod($method);
	}

	public function getMask(): string|null
	{
		return $this->route->getMask();
	}

	public function setMask(string|null $mask): void
	{
		$this->route->setMask($mask);
	}

	public function getPattern(): string
	{
		return $this->route->getPattern();
	}

	public function setPattern(string|null $pattern): void
	{
		$this->route->setPattern($pattern);
	}

	// === Parameters (delegated to HandlerDefinition) ===

	/**
	 * @return array<EndpointParameter>
	 */
	public function getParameters(): array
	{
		return $this->handler->getParameters();
	}

	/**
	 * @return array<EndpointParameter>
	 */
	public function getParametersByIn(string $in): array
	{
		return $this->handler->getParametersByIn($in);
	}

	public function hasParameter(string $name): bool
	{
		return $this->handler->hasParameter($name);
	}

	public function addParameter(EndpointParameter $param): void
	{
		$this->handler->addParameter($param);
	}

	/**
	 * @param array<EndpointParameter> $parameters
	 */
	public function setParameters(array $parameters): void
	{
		$this->handler->setParameters($parameters);
	}

	// === Request Body (delegated to HandlerDefinition) ===

	public function setRequestBody(EndpointRequestBody|null $requestBody): void
	{
		$this->handler->setRequestBody($requestBody);
	}

	public function getRequestBody(): EndpointRequestBody|null
	{
		return $this->handler->getRequestBody();
	}

	// === Responses (delegated to OpenApiDefinition) ===

	/**
	 * @return array<EndpointResponse>
	 */
	public function getResponses(): array
	{
		return $this->openApi->getResponses();
	}

	public function hasResponse(string $code): bool
	{
		return $this->openApi->hasResponse($code);
	}

	public function addResponse(EndpointResponse $response): void
	{
		$this->openApi->addResponse($response);
	}

	/**
	 * @param array<EndpointResponse> $responses
	 */
	public function setResponses(array $responses): void
	{
		$this->openApi->setResponses($responses);
	}

	// === OpenAPI spec (delegated to OpenApiDefinition) ===

	/**
	 * @return array<string, mixed>
	 */
	public function getOpenApi(): array
	{
		return $this->openApi->getOpenApi();
	}

	/**
	 * @param array<string, mixed> $openApi
	 */
	public function setOpenApi(array $openApi): void
	{
		$this->openApi->setOpenApi($openApi);
	}

	// === Tags (delegated to OpenApiDefinition) ===

	/**
	 * @return array<string, mixed>
	 */
	public function getTags(): array
	{
		return $this->openApi->getTags();
	}

	public function getTag(string $name): mixed
	{
		return $this->openApi->getTag($name);
	}

	public function hasTag(string $name): bool
	{
		return $this->openApi->hasTag($name);
	}

	public function addTag(string $name, mixed $value = null): void
	{
		$this->openApi->addTag($name, $value);
	}

	// === Summary and Deprecated (delegated to OpenApiDefinition) ===

	public function getSummary(): string|null
	{
		return $this->openApi->getSummary();
	}

	public function setSummary(string|null $summary): void
	{
		$this->openApi->setSummary($summary);
	}

	public function isDeprecated(): bool
	{
		return $this->openApi->isDeprecated();
	}

	public function setDeprecated(bool $deprecated): void
	{
		$this->openApi->setDeprecated($deprecated);
	}

}
