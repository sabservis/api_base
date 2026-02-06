<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use function array_filter;

/**
 * Handler definition - contains controller/method and parameter information for request handling.
 */
final class HandlerDefinition
{

	/** @var array<EndpointParameter> */
	private array $parameters = [];

	/** @var array<EndpointAuthorization> */
	private array $authorizations = [];

	private EndpointRequestBody|null $requestBody = null;

	/**
	 * @param class-string $controllerClass
	 */
	public function __construct(
		private string $controllerClass,
		private string $controllerMethod,
	)
	{
	}

	/**
	 * @return class-string
	 */
	public function getControllerClass(): string
	{
		return $this->controllerClass;
	}

	public function getControllerMethod(): string
	{
		return $this->controllerMethod;
	}

	/**
	 * @return array<EndpointParameter>
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	/**
	 * @return array<EndpointParameter>
	 */
	public function getParametersByIn(string $in): array
	{
		return array_filter(
			$this->getParameters(),
			static fn (EndpointParameter $parameter): bool => $parameter->getIn() === $in,
		);
	}

	public function hasParameter(string $name): bool
	{
		return isset($this->parameters[$name]);
	}

	public function addParameter(EndpointParameter $param): void
	{
		$this->parameters[$param->getName()] = $param;
	}

	/**
	 * @param array<EndpointParameter> $parameters
	 */
	public function setParameters(array $parameters): void
	{
		foreach ($parameters as $param) {
			$this->addParameter($param);
		}
	}

	public function getRequestBody(): EndpointRequestBody|null
	{
		return $this->requestBody;
	}

	public function setRequestBody(EndpointRequestBody|null $requestBody): void
	{
		$this->requestBody = $requestBody;
	}

	/**
	 * @return array<EndpointAuthorization>
	 */
	public function getAuthorizations(): array
	{
		return $this->authorizations;
	}

	public function hasAuthorizations(): bool
	{
		return $this->authorizations !== [];
	}

	public function addAuthorization(EndpointAuthorization $authorization): void
	{
		$key = $authorization->getAuthorizer() . "\0" . $authorization->getActivity();
		$this->authorizations[$key] = $authorization;
	}

	/**
	 * @param array<EndpointAuthorization> $authorizations
	 */
	public function setAuthorizations(array $authorizations): void
	{
		foreach ($authorizations as $authorization) {
			$this->addAuthorization($authorization);
		}
	}

}
