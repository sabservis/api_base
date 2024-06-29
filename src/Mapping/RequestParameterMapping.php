<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use BackedEnum;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Types\TypeNormalizer;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Throwable;
use UnitEnum;
use function array_change_key_case;
use function array_key_exists;
use function assert;
use function is_subclass_of;
use function sprintf;
use function strtolower;
use function ucfirst;

class RequestParameterMapping
{

	public function __construct(protected NormalizerProvider $normalizer)
	{
	}

	public function map(ApiRequest $request, ApiResponse $response): ApiRequest
	{
		$endpoint = $request->getAttribute(RequestAttributes::Endpoint);
		assert($endpoint instanceof Endpoint || $endpoint === null);

		// Validate that we have an endpoint
		if ($endpoint === null) {
			throw new InvalidStateException(sprintf('Attribute "%s" is required', RequestAttributes::Endpoint));
		}

		// Get all parameters
		$parameters = $endpoint->getParameters();

		// Skip, if there are no parameters
		if ($parameters === []) {
			return $request;
		}

		$headerParameters = array_change_key_case($request->getHeaders());
		$cookieParams = $request->getCookieParams();
		// Get request parameters from attribute
		$requestParameters = $request->getAttribute(RequestAttributes::Parameters);

		// Iterate over all parameters
		foreach ($parameters as $parameter) {
			switch ($parameter->getIn()) {
				case $parameter::InPath:
				case $parameter::InQuery:
					// Logical check
					if (!array_key_exists($parameter->getName(), $requestParameters)) {
						if (!$parameter->isRequired()) {
							break;
						}

						throw new ClientErrorException(sprintf(
							'%s request parameter "%s" should be provided.',
							ucfirst($parameter->getIn()),
							$parameter->getName(),
						));
					}

					// Obtain request parameter values
					$value = $requestParameters[$parameter->getName()];

					$this->checkParameterProvided($parameter, $value);
					$this->checkParameterNotEmpty($parameter, $value);

					// Normalize value
					$normalizedValue = $this->denormalize($value, $parameter);

					// Update requests
					$requestParameters[$parameter->getName()] = $normalizedValue;

					$request = $request->withAttribute(RequestAttributes::Parameters, $requestParameters);

					break;
				case $parameter::InCookie:
					// Logical check
					if (!array_key_exists($parameter->getName(), $cookieParams)) {
						if (!$parameter->isRequired()) {
							break;
						}

						throw new ClientErrorException(sprintf(
							'%s request parameter "%s" should be provided.',
							ucfirst($parameter->getIn()),
							$parameter->getName(),
						));
					}

					// Obtain request parameter values
					$value = $cookieParams[$parameter->getName()];

					$this->checkParameterProvided($parameter, $value);
					$this->checkParameterNotEmpty($parameter, $value);

					// Normalize value
					$normalizedValue = $this->denormalize($value, $parameter);

					// Update requests
					$cookieParams[$parameter->getName()] = $normalizedValue;
					$request = $request->withCookieParams($cookieParams);

					break;
				case $parameter::InHeader:
					$headerParameterName = strtolower($parameter->getName());

					// Logical check
					if (!array_key_exists($headerParameterName, $headerParameters)) {
						if (!$parameter->isRequired()) {
							break;
						}

						throw new ClientErrorException(sprintf(
							'%s request parameter "%s" should be provided.',
							ucfirst($parameter->getIn()),
							$parameter->getName(),
						));
					}

					// Obtain request parameter values
					$values = $headerParameters[$headerParameterName];
					$normalizedValues = [];

					// Normalize value
					foreach ($values as $index => $value) {
						$this->checkParameterNotEmpty($parameter, $value);
						$normalizedValues[$index] = $this->denormalize($value, $parameter);
					}

					// Update requests
					$headerParameters[$headerParameterName] = $normalizedValues;
					$request = $request->withHeader($headerParameterName, $normalizedValues);

					break;
			}
		}

		return $request;
	}

	protected function checkParameterProvided(EndpointParameter $parameter, mixed $value): void
	{
		if ($value === null && $parameter->isRequired()) {
			throw new ClientErrorException(sprintf(
				'%s request parameter "%s" should be provided.',
				ucfirst($parameter->getIn()),
				$parameter->getName(),
			));
		}
	}

	protected function checkParameterNotEmpty(EndpointParameter $parameter, mixed $value): void
	{
		if ($value === '' && !$parameter->isAllowEmpty()) {
			throw new ClientErrorException(sprintf(
				'%s request parameter "%s" should not be empty.',
				ucfirst($parameter->getIn()),
				$parameter->getName(),
			));
		}
	}

	protected function denormalize(mixed $value, EndpointParameter $parameter): mixed
	{
		if ($value === '' || $value === null) {
			return null;
		}

		$type = $parameter->getType();
		$args = [];

		if (is_subclass_of($type, BackedEnum::class)) {
			$type = BackedEnum::class;
			$args[] = $parameter->getType();
		} elseif (is_subclass_of($type, UnitEnum::class)) {
			$type = UnitEnum::class;
			$args[] = $parameter->getType();
		}

		try {
			$normalizer = $this->normalizer->findNormalizer($type);

			return $normalizer instanceof TypeNormalizer ? $normalizer->denormalize($value, ...$args) : $value;
		} catch (Throwable) {
			throw new ClientErrorException(sprintf(
				'%s request parameter "%s" should be of type "%s"%s.',
				ucfirst($parameter->getIn()),
				$parameter->getName(),
				$parameter->getType(),
				$parameter->getDescription() !== null ? ' ' . $parameter->getDescription() : '',
			));
		}
	}

}
