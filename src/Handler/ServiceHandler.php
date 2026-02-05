<?php declare(strict_types = 1);

namespace Sabservis\Api\Handler;

use Nette\DI\Container;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\UI\Controller\Controller;
use function array_slice;
use function assert;
use function class_exists;
use function count;
use function in_array;
use function is_object;
use function sprintf;

class ServiceHandler
{

	private const SCALAR_TYPES = ['int', 'float', 'string', 'bool', 'array', 'mixed', 'null', 'object'];

	/**
	 * Maximum number of cached method reflections.
	 * Prevents unbounded memory growth in long-running processes.
	 */
	private const REFLECTION_CACHE_MAX_SIZE = 500;

	/**
	 * In-memory reflection cache with bounded size.
	 *
	 * @var array<string, array<ReflectionParameter>>
	 */
	private array $reflectionCache = [];

	public function __construct(
		protected Container $container,
		private readonly EntitySerializer|null $serializer = null,
		private readonly EntityValidator|null $validator = null,
	)
	{
	}

	public function handle(ApiRequest $request, ApiResponse $response): mixed
	{
		$endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
		assert($endpoint instanceof Endpoint);

		$service = $this->container->getByType($endpoint->getControllerClass());

		if (!($service instanceof Controller)) {
			throw new InvalidArgumentException(
				sprintf('Controller "%s" must implement "%s"', $endpoint->getControllerClass(), Controller::class),
			);
		}

		$arguments = $this->resolveArguments($request, $response, $endpoint);

		return $service->{$endpoint->getControllerMethod()}(...$arguments);
	}

	/**
	 * Resolves method arguments from request parameters.
	 *
	 * Supports:
	 * - ApiRequest, ApiResponse - injected directly
	 * - DTO classes - deserialized from request body
	 * - Named parameters from path/query - resolved from request->getParameter()
	 *
	 * @return array<mixed>
	 */
	private function resolveArguments(ApiRequest $request, ApiResponse $response, Endpoint $endpoint): array
	{
		$parameters = $this->getMethodParameters($endpoint);

		$arguments = [];
		$entityInjected = false;

		foreach ($parameters as $param) {
			$paramName = $param->getName();
			$paramType = $param->getType();

			// Get the type name (for union types, use first type)
			$typeName = null;
			if ($paramType instanceof ReflectionNamedType) {
				$typeName = $paramType->getName();
			}

			// Inject ApiRequest
			if ($typeName === ApiRequest::class) {
				$arguments[] = $request;

				continue;
			}

			// Inject ApiResponse
			if ($typeName === ApiResponse::class) {
				$arguments[] = $response;

				continue;
			}

			// Try to resolve from request parameters (path/query)
			$value = $request->getParameter($paramName, null);

			if ($value !== null) {
				$arguments[] = $value;

				continue;
			}

			// Try to inject DTO from request body (only once per method)
			if (!$entityInjected && $typeName !== null && $this->isDtoClass($typeName)) {
				$entity = $this->resolveEntity($request, $typeName);

				if ($entity !== null) {
					$arguments[] = $entity;
					$entityInjected = true;

					continue;
				}
			}

			// Parameter not found - check if optional
			if ($param->isOptional()) {
				$arguments[] = $param->getDefaultValue();

				continue;
			}

			// Required parameter not found - throw clear exception
			throw new InvalidArgumentException(
				sprintf(
					'Required parameter "%s" (type: %s) not found in request for %s::%s(). ' .
					'Check that the parameter is defined in path, query, or request body.',
					$paramName,
					$typeName ?? 'mixed',
					$endpoint->getControllerClass(),
					$endpoint->getControllerMethod(),
				),
			);
		}

		return $arguments;
	}

	/**
	 * Get method parameters with bounded in-memory caching.
	 *
	 * Cache is limited to REFLECTION_CACHE_MAX_SIZE entries to prevent
	 * unbounded memory growth in long-running processes.
	 *
	 * @return array<ReflectionParameter>
	 */
	private function getMethodParameters(Endpoint $endpoint): array
	{
		$cacheKey = $endpoint->getControllerClass() . '::' . $endpoint->getControllerMethod();

		if (isset($this->reflectionCache[$cacheKey])) {
			return $this->reflectionCache[$cacheKey];
		}

		$reflection = new ReflectionMethod($endpoint->getControllerClass(), $endpoint->getControllerMethod());
		$parameters = $reflection->getParameters();

		// Evict oldest entries if cache is full (simple FIFO eviction)
		if (count($this->reflectionCache) >= self::REFLECTION_CACHE_MAX_SIZE) {
			// Remove first 10% of entries
			$this->reflectionCache = array_slice(
				$this->reflectionCache,
				(int) (self::REFLECTION_CACHE_MAX_SIZE * 0.1),
				null,
				true,
			);
		}

		$this->reflectionCache[$cacheKey] = $parameters;

		return $parameters;
	}

	/**
	 * Check if type is a DTO class (not scalar, not framework class).
	 */
	private function isDtoClass(string $typeName): bool
	{
		// Scalar types
		if (in_array($typeName, self::SCALAR_TYPES, true)) {
			return false;
		}

		// Framework classes
		if ($typeName === ApiRequest::class || $typeName === ApiResponse::class) {
			return false;
		}

		// Must be an existing class
		if (!class_exists($typeName)) {
			return false;
		}

		// Must not be internal PHP class
		$reflection = new ReflectionClass($typeName);

		return !$reflection->isInternal();
	}

	/**
	 * Resolve entity from request - either already deserialized or deserialize now.
	 */
	private function resolveEntity(ApiRequest $request, string $typeName): object|null
	{
		// Check if already deserialized (from ApiDispatcher)
		$entity = $request->getAttribute(RequestAttributes::RequestEntity->value);

		if ($entity !== null && $entity instanceof $typeName) {
			return $entity;
		}

		// Deserialize from request body if serializer available
		if ($this->serializer === null) {
			return null;
		}

		$body = $request->getContents();

		if ($body === '') {
			return null;
		}

		$entity = $this->serializer->deserialize($body, $typeName);

		if (!is_object($entity)) {
			return null;
		}

		// Validate if validator available
		if ($this->validator !== null) {
			$this->validator->validate($entity);
		}

		return $entity;
	}

}
