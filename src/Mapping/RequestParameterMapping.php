<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use function array_change_key_case;
use function array_key_exists;
use function assert;
use function sprintf;
use function strtolower;
use function ucfirst;

/**
 * Maps request parameters from path/query/header/cookie to typed values.
 *
 * Responsibilities:
 * - Locates parameters in the request (path, query, header, cookie)
 * - Validates required parameters are present
 * - Validates non-empty constraints
 * - Delegates type conversion to ParameterTypeConverter
 * - Updates request with converted values
 */
class RequestParameterMapping
{

	private ParameterTypeConverter $converter;

	public function __construct(ParameterTypeConverter|null $converter = null)
	{
		$this->converter = $converter ?? new ParameterTypeConverter();
	}

	public function map(ApiRequest $request): ApiRequest
	{
		$endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
		assert($endpoint instanceof Endpoint || $endpoint === null);

		if ($endpoint === null) {
			throw new InvalidStateException(sprintf('Attribute "%s" is required', RequestAttributes::Endpoint->value));
		}

		$parameters = $endpoint->getParameters();

		if ($parameters === []) {
			return $request;
		}

		$sources = $this->prepareSources($request);

		foreach ($parameters as $parameter) {
			$request = $this->processParameter($request, $parameter, $sources);
		}

		return $request;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function prepareSources(ApiRequest $request): array
	{
		// Use separate path/query attributes if available, fallback to combined for backward compatibility
		$pathParams = $request->getAttribute(RequestAttributes::PathParameters->value);
		$queryParams = $request->getAttribute(RequestAttributes::QueryParameters->value);
		$combinedParams = $request->getAttribute(RequestAttributes::Parameters->value, []);

		return [
			EndpointParameter::InPath => $pathParams ?? $combinedParams,
			EndpointParameter::InQuery => $queryParams ?? $combinedParams,
			EndpointParameter::InCookie => $request->getCookies(),
			EndpointParameter::InHeader => array_change_key_case($request->getHeaders()),
		];
	}

	/**
	 * @param array<string, array<string, mixed>> $sources
	 */
	private function processParameter(ApiRequest $request, EndpointParameter $parameter, array $sources): ApiRequest
	{
		$in = $parameter->getIn();
		$name = $in === EndpointParameter::InHeader ? strtolower($parameter->getName()) : $parameter->getName();
		$source = $sources[$in] ?? [];

		if (!array_key_exists($name, $source)) {
			if ($parameter->isRequired()) {
				throw new ClientErrorException(sprintf(
					ErrorMessages::PARAMETER_REQUIRED,
					ucfirst($in),
					$parameter->getName(),
				));
			}

			return $request;
		}

		$value = $source[$name];

		if ($in !== EndpointParameter::InHeader) {
			$this->validateProvided($parameter, $value);
		}

		$this->validateNotEmpty($parameter, $value);

		$convertedValue = $this->converter->convert($value, $parameter->getType(), $parameter);

		return $this->updateRequest($request, $parameter, $name, $convertedValue);
	}

	private function validateProvided(EndpointParameter $parameter, mixed $value): void
	{
		if ($value === null && $parameter->isRequired()) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_REQUIRED,
				ucfirst($parameter->getIn()),
				$parameter->getName(),
			));
		}
	}

	private function validateNotEmpty(EndpointParameter $parameter, mixed $value): void
	{
		if ($value === '' && !$parameter->isAllowEmptyValue()) {
			throw new ClientErrorException(sprintf(
				ErrorMessages::PARAMETER_EMPTY,
				ucfirst($parameter->getIn()),
				$parameter->getName(),
			));
		}
	}

	private function updateRequest(
		ApiRequest $request,
		EndpointParameter $parameter,
		string $name,
		mixed $value,
	): ApiRequest
	{
		return match ($parameter->getIn()) {
			EndpointParameter::InPath => $request
				->withAttribute(
					RequestAttributes::PathParameters->value,
					[$name => $value] + $request->getAttribute(RequestAttributes::PathParameters->value, []),
				)
				->withAttribute(
					RequestAttributes::Parameters->value,
					[$name => $value] + $request->getAttribute(RequestAttributes::Parameters->value, []),
				),
			EndpointParameter::InQuery => $request
				->withAttribute(
					RequestAttributes::QueryParameters->value,
					[$name => $value] + $request->getAttribute(RequestAttributes::QueryParameters->value, []),
				)
				->withAttribute(
					RequestAttributes::Parameters->value,
					[$name => $value] + $request->getAttribute(RequestAttributes::Parameters->value, []),
				),
			EndpointParameter::InCookie => $request->withCookies(
				[$name => $value] + $request->getCookies(),
			),
			EndpointParameter::InHeader => $request->withHeader($name, (string) $value),
			default => $request,
		};
	}

}
