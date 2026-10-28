<?php declare(strict_types = 1);

namespace Tests\Unit\DI;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Attributes\MapProperty;
use Pocta\DataMapper\Validation\ValidatorResolverInterface;
use ReflectionProperty;
use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\DI\ApiExtension;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\ErrorHandler\ErrorContextFilter;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\ErrorHandler\ErrorResponseBuilder;
use Sabservis\Api\ErrorHandler\ErrorResponseTransformer;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\ErrorHandler\TraceIdProvider;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\ContainerValidatorResolver;
use Sabservis\Api\Mapping\Validator\DataMapperEntityValidator;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Middleware\ApiMiddleware;
use Sabservis\Api\Middleware\Middleware;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Security\AuthorizationChecker;
use Sabservis\Api\Utils\ChainBuilder;
use Throwable;
use function array_filter;
use function array_map;
use function array_values;
use function assert;
use function base64_encode;
use function file_put_contents;
use function is_array;
use function json_decode;
use function sys_get_temp_dir;
use function uniqid;

final class ApiExtensionTest extends TestCase
{

	#[Test]
	public function registersRequiredServices(): void
	{
		$container = $this->createContainer([]);

		self::assertTrue($container->hasService('api.application'));
		self::assertTrue($container->hasService('api.dispatcher'));
		self::assertTrue($container->hasService('api.errorHandler'));
		self::assertTrue($container->hasService('api.errorResponseBuilder'));
		self::assertTrue($container->hasService('api.router'));
		self::assertTrue($container->hasService('api.handler'));
		self::assertTrue($container->hasService('api.schema'));
		self::assertTrue($container->hasService('api.authorization.checker'));
		self::assertTrue($container->hasService('api.request.parameters.mapping'));
	}

	#[Test]
	public function createsCoreServicesWithCorrectTypes(): void
	{
		$container = $this->createContainer([]);

		self::assertInstanceOf(ApiApplication::class, $container->getService('api.application'));
		self::assertInstanceOf(ApiDispatcher::class, $container->getService('api.dispatcher'));
		self::assertInstanceOf(ErrorHandler::class, $container->getService('api.errorHandler'));
		self::assertInstanceOf(ErrorResponseBuilder::class, $container->getService('api.errorResponseBuilder'));
		self::assertInstanceOf(Router::class, $container->getService('api.router'));
		self::assertInstanceOf(ServiceHandler::class, $container->getService('api.handler'));
		self::assertInstanceOf(Schema::class, $container->getService('api.schema'));
		self::assertInstanceOf(AuthorizationChecker::class, $container->getService('api.authorization.checker'));
		self::assertInstanceOf(
			RequestParameterMapping::class,
			$container->getService('api.request.parameters.mapping'),
		);
	}

	#[Test]
	public function usesSimpleErrorHandlerByDefault(): void
	{
		$container = $this->createContainer([]);

		$errorHandler = $container->getService('api.errorHandler');
		self::assertInstanceOf(SimpleErrorHandler::class, $errorHandler);
	}

	#[Test]
	public function corsMiddlewareNotRegisteredWhenDisabled(): void
	{
		$container = $this->createContainer([
			'cors' => [
				'enabled' => false,
			],
		]);

		self::assertFalse($container->hasService('api.middleware.cors'));
	}

	#[Test]
	public function corsMiddlewareRegisteredWhenEnabled(): void
	{
		$container = $this->createContainer([
			'cors' => [
				'enabled' => true,
				'allowedOrigins' => ['https://example.com'],
			],
		]);

		self::assertTrue($container->hasService('api.middleware.cors'));
	}

	#[Test]
	public function throwsExceptionForCorsCredentialsWithoutOrigins(): void
	{
		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('CORS: allowCredentials requires explicit allowedOrigins');

		$this->createContainer([
			'cors' => [
				'enabled' => true,
				'allowCredentials' => true,
				'allowedOrigins' => [],
			],
		]);
	}

	#[Test]
	public function setsMaxRequestBodySize(): void
	{
		$container = $this->createContainer([
			'maxRequestBodySize' => 1_024_000,
		]);

		$application = $container->getService('api.application');
		self::assertInstanceOf(ApiApplication::class, $application);
	}

	#[Test]
	public function throwsForMalformedTrustedProxy(): void
	{
		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage(
			'Invalid trustedProxies entry "10.0.0.0/". Expected IP address or CIDR (e.g. 10.0.0.0/8).',
		);

		$this->createContainer([
			'trustedProxies' => ['127.0.0.1', '10.0.0.0/'],
		]);
	}

	#[Test]
	public function setsTrustedProxies(): void
	{
		$container = $this->createContainer([
			'trustedProxies' => ['127.0.0.1', '10.0.0.0/8'],
		]);

		$application = $container->getService('api.application');
		self::assertInstanceOf(ApiApplication::class, $application);
	}

	#[Test]
	public function registersValidatorByDefault(): void
	{
		$container = $this->createContainer([]);

		self::assertTrue($container->hasService('api.request.entity.validator'));

		$validator = $container->getService('api.request.entity.validator');
		self::assertInstanceOf(EntityValidator::class, $validator);
		self::assertInstanceOf(DataMapperEntityValidator::class, $validator);
	}

	#[Test]
	public function validatorCanBeDisabledWithNull(): void
	{
		$container = $this->createContainer([
			'validator' => null,
		]);

		self::assertFalse($container->hasService('api.request.entity.validator'));
	}

	#[Test]
	public function registersValidatorResolverByDefault(): void
	{
		$container = $this->createContainer([]);

		self::assertTrue($container->hasService('api.mapping.validatorResolver'));

		$resolver = $container->getService('api.mapping.validatorResolver');
		self::assertInstanceOf(ContainerValidatorResolver::class, $resolver);
		self::assertInstanceOf(ValidatorResolverInterface::class, $resolver);
	}

	#[Test]
	public function validatorResolverCanBeDisabledWithNull(): void
	{
		$container = $this->createContainer([
			'validatorResolver' => null,
		]);

		self::assertFalse($container->hasService('api.mapping.validatorResolver'));
	}

	#[Test]
	public function validatorResolverIsInjectedIntoSerializer(): void
	{
		$container = $this->createContainer([]);

		$serializer = $container->getByType(EntitySerializer::class);
		self::assertInstanceOf(DataMapperSerializer::class, $serializer);
	}

	#[Test]
	public function serializerWorksWhenValidatorResolverDisabled(): void
	{
		$container = $this->createContainer([
			'validatorResolver' => null,
		]);

		$serializer = $container->getByType(EntitySerializer::class);
		self::assertInstanceOf(DataMapperSerializer::class, $serializer);
	}

	#[Test]
	public function setsRouterBasePath(): void
	{
		$container = $this->createContainer([
			'router' => [
				'basePath' => '/api/v1',
			],
		]);

		self::assertTrue($container->hasService('api.router'));
	}

	// === ErrorResponseBuilder DI Tests ===

	#[Test]
	public function registersErrorResponseBuilder(): void
	{
		$container = $this->createContainer([]);

		self::assertTrue($container->hasService('api.errorResponseBuilder'));
		self::assertInstanceOf(
			ErrorResponseBuilder::class,
			$container->getService('api.errorResponseBuilder'),
		);
	}

	#[Test]
	public function errorResponseBuilderIsInjectedIntoErrorHandler(): void
	{
		$container = $this->createContainer([]);

		$builder = $container->getService('api.errorResponseBuilder');
		$errorHandler = $container->getService('api.errorHandler');

		$ref = new ReflectionProperty(SimpleErrorHandler::class, 'responseBuilder');
		self::assertSame($builder, $ref->getValue($errorHandler));
	}

	#[Test]
	public function autowiresTraceIdProviderIntoBuilder(): void
	{
		$container = $this->createContainerWithServices([
			TestTraceIdProvider::class,
		]);

		$builder = $container->getService('api.errorResponseBuilder');
		self::assertInstanceOf(ErrorResponseBuilder::class, $builder);

		$response = $builder->build(new ClientErrorException('Test', 400));
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('traceId', $body);
		self::assertSame('test-trace-id', $body['traceId']);
	}

	#[Test]
	public function autowiresErrorContextFilterIntoBuilder(): void
	{
		$container = $this->createContainerWithServices([
			TestErrorContextFilter::class,
		]);

		$builder = $container->getService('api.errorResponseBuilder');
		self::assertInstanceOf(ErrorResponseBuilder::class, $builder);

		$response = $builder->build(
			(new ClientErrorException('Test', 400))->withContext(['keep' => 'yes', 'strip_me' => 'gone']),
		);
		$body = json_decode($response->getBody(), true);

		self::assertArrayHasKey('keep', $body['context']);
		self::assertArrayNotHasKey('strip_me', $body['context']);
	}

	#[Test]
	public function autowiresErrorResponseTransformerIntoBuilder(): void
	{
		$container = $this->createContainerWithServices([
			TestErrorResponseTransformer::class,
		]);

		$builder = $container->getService('api.errorResponseBuilder');
		self::assertInstanceOf(ErrorResponseBuilder::class, $builder);

		$response = $builder->build(new ClientErrorException('Test', 400));
		$body = json_decode($response->getBody(), true);

		self::assertSame('https://support.example.com', $body['support']);
	}

	#[Test]
	public function disableContextViaConfig(): void
	{
		$container = $this->createContainer([
			'errorResponse' => [
				'includeContext' => false,
			],
		]);

		$builder = $container->getService('api.errorResponseBuilder');
		self::assertInstanceOf(ErrorResponseBuilder::class, $builder);

		$response = $builder->build(
			(new ClientErrorException('Test', 400))->withContext(['field' => 'email']),
		);
		$body = json_decode($response->getBody(), true);

		self::assertArrayNotHasKey('context', $body);
	}

	#[Test]
	public function defaultConfigPreservesExistingBehavior(): void
	{
		$container = $this->createContainer([]);

		$builder = $container->getService('api.errorResponseBuilder');
		self::assertInstanceOf(ErrorResponseBuilder::class, $builder);

		$response = $builder->build(
			(new ClientErrorException('Test error', 400))->withContext(['field' => 'email']),
		);
		$body = json_decode($response->getBody(), true);

		self::assertSame(400, $body['code']);
		self::assertSame('Test error', $body['message']);
		self::assertArrayHasKey('context', $body);
		self::assertArrayNotHasKey('traceId', $body);
	}

	#[Test]
	public function middlewareAsClassNameUsesPriorityFromAttribute(): void
	{
		$order = $this->compileMiddlewareOrder(<<<'NEON'
			api:
			    middlewares:
			        - Tests\Unit\DI\LatePriorityMiddleware
			        - Tests\Unit\DI\ReferencePriorityMiddleware
			NEON);

		self::assertSame([ReferencePriorityMiddleware::class, LatePriorityMiddleware::class], $order);
	}

	#[Test]
	public function middlewareAsStatementWithArgumentsUsesPriorityFromAttribute(): void
	{
		$order = $this->compileMiddlewareOrder(<<<'NEON'
			api:
			    middlewares:
			        - Tests\Unit\DI\LatePriorityMiddleware(limit: 100)
			        - Tests\Unit\DI\ReferencePriorityMiddleware
			NEON);

		self::assertSame([ReferencePriorityMiddleware::class, LatePriorityMiddleware::class], $order);
	}

	#[Test]
	public function middlewareAsStructureWithoutPriorityUsesPriorityFromAttribute(): void
	{
		$order = $this->compileMiddlewareOrder(<<<'NEON'
			api:
			    middlewares:
			        - { class: Tests\Unit\DI\LatePriorityMiddleware }
			        - Tests\Unit\DI\ReferencePriorityMiddleware
			NEON);

		self::assertSame([ReferencePriorityMiddleware::class, LatePriorityMiddleware::class], $order);
	}

	#[Test]
	public function middlewareAsStructureWithStatementUsesExplicitPriority(): void
	{
		$order = $this->compileMiddlewareOrder(<<<'NEON'
			api:
			    middlewares:
			        - Tests\Unit\DI\ReferencePriorityMiddleware
			        - { class: Tests\Unit\DI\LatePriorityMiddleware(limit: 100), priority: 100 }
			NEON);

		self::assertSame([LatePriorityMiddleware::class, ReferencePriorityMiddleware::class], $order);
	}

	#[Test]
	public function middlewareAsServiceReferenceUsesExplicitPriority(): void
	{
		$order = $this->compileMiddlewareOrder(<<<'NEON'
			services:
			    lateMiddleware: Tests\Unit\DI\LatePriorityMiddleware(limit: 100)

			api:
			    middlewares:
			        - Tests\Unit\DI\ReferencePriorityMiddleware
			        - { class: @lateMiddleware, priority: 100 }
			NEON);

		self::assertSame([LatePriorityMiddleware::class, ReferencePriorityMiddleware::class], $order);
	}

	#[Test]
	public function defaultSerializerKeepsDataMapperDefaults(): void
	{
		$serializer = $this->createContainer([])->getByType(EntitySerializer::class);
		assert($serializer instanceof EntitySerializer);

		self::assertSame(
			'{"city":"Prague","secret":"s"}',
			$serializer->serialize(new SerializerHardeningDto()),
		);
	}

	#[Test]
	public function serializerAcceptsHardenedMapperOptionsFromNeon(): void
	{
		$container = $this->createContainerFromNeon(<<<'NEON'
			api:
			    serializer: Sabservis\Api\Mapping\Serializer\DataMapperSerializer(
			        Pocta\DataMapper\MapperOptions(publicPropertiesOnly: true, symmetricPropertyPaths: true)
			    )
			NEON);

		$serializer = $container->getByType(EntitySerializer::class);
		assert($serializer instanceof EntitySerializer);

		self::assertSame(
			'{"address":{"city":"Prague"}}',
			$serializer->serialize(new SerializerHardeningDto()),
		);

		$dto = $serializer->deserialize(
			'{"secret":"injected","address":{"city":"Brno"}}',
			SerializerHardeningDto::class,
		);
		assert($dto instanceof SerializerHardeningDto);

		self::assertSame('s', $dto->getSecret());
		self::assertSame('Brno', $dto->city);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function createContainer(array $config): Container
	{
		$tempDir = sys_get_temp_dir() . '/nette-test-' . uniqid('', true);
		@mkdir($tempDir, 0o777, true); // @phpcs:ignore

		$loader = new ContainerLoader($tempDir, true);
		// phpcs:ignore SlevomatCodingStandard.Functions.StaticClosure.ClosureNotStatic
		$callback = function (Compiler $compiler) use ($config): void {
			$compiler->addExtension('api', new ApiExtension());
			$compiler->addConfig([
				'parameters' => [
					'productionMode' => false,
				],
				'api' => $config,
			]);
		};
		/** @var class-string<Container> $class */
		$class = $loader->load($callback, uniqid('container_', true));

		return new $class();
	}

	#[Test]
	public function failedBasicAuthAttemptsAreRateLimited(): void
	{
		$container = $this->createContainerFromNeon(<<<'NEON'
			api:
			    middlewares:
			        - Sabservis\Api\Middleware\BasicAuthMiddleware
			        - Sabservis\Api\Middleware\RateLimitMiddleware(cache: Symfony\Component\Cache\Adapter\ArrayAdapter(), maxRequests: 2)
			NEON);

		$chainBuilder = $container->getService('api.middleware.chain');
		assert($chainBuilder instanceof ChainBuilder);
		$chain = $chainBuilder->create();

		$request = new ApiRequest(
			method: 'GET',
			uri: '/admin',
			headers: ['Authorization' => 'Basic ' . base64_encode('admin:wrong')],
			serverParams: ['REMOTE_ADDR' => '1.2.3.4'],
		);

		self::assertSame(401, $chain($request, new ApiResponse())->getStatusCode());
		self::assertSame(401, $chain($request, new ApiResponse())->getStatusCode());

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(429);

		$chain($request, new ApiResponse());
	}

	#[Test]
	public function httpsIsEnforcedBeforeAuthentication(): void
	{
		$container = $this->createContainerFromNeon(<<<'NEON'
			api:
			    middlewares:
			        - Sabservis\Api\Middleware\BasicAuthMiddleware
			        - Sabservis\Api\Middleware\EnforceHttpsMiddleware
			NEON);

		$chainBuilder = $container->getService('api.middleware.chain');
		assert($chainBuilder instanceof ChainBuilder);
		$chain = $chainBuilder->create();

		// Plain HTTP without credentials: must not trigger WWW-Authenticate challenge
		$response = $chain(new ApiRequest(method: 'GET', uri: '/admin'), new ApiResponse());
		self::assertSame(400, $response->getStatusCode());
		self::assertFalse($response->hasHeader('WWW-Authenticate'));

		// Plain HTTP with wrong credentials: same response, no password oracle
		$request = new ApiRequest(
			method: 'GET',
			uri: '/admin',
			headers: ['Authorization' => 'Basic ' . base64_encode('admin:wrong')],
		);
		self::assertSame(400, $chain($request, new ApiResponse())->getStatusCode());
	}

	/**
	 * Compiles container from NEON config and returns middleware classes in chain order
	 * (excluding the always-last ApiMiddleware).
	 *
	 * @return list<class-string>
	 */
	private function compileMiddlewareOrder(string $neon): array
	{
		$container = $this->createContainerFromNeon($neon);

		$chain = $container->getService('api.middleware.chain');
		$middlewares = (new ReflectionProperty(ChainBuilder::class, 'middlewares'))->getValue($chain);
		assert(is_array($middlewares));

		$classes = array_map(static fn (object $middleware): string => $middleware::class, $middlewares);

		return array_values(array_filter($classes, static fn (string $class): bool => $class !== ApiMiddleware::class));
	}

	private function createContainerFromNeon(string $neon): Container
	{
		$tempDir = sys_get_temp_dir() . '/nette-test-' . uniqid('', true);
		@mkdir($tempDir, 0o777, true); // @phpcs:ignore
		$configFile = $tempDir . '/config.neon';
		file_put_contents($configFile, $neon);

		$loader = new ContainerLoader($tempDir, true);
		// phpcs:ignore SlevomatCodingStandard.Functions.StaticClosure.ClosureNotStatic
		$callback = function (Compiler $compiler) use ($configFile): void {
			$compiler->addExtension('api', new ApiExtension());
			$compiler->addConfig(['parameters' => ['productionMode' => false]]);
			$compiler->loadConfig($configFile);
		};
		/** @var class-string<Container> $class */
		$class = $loader->load($callback, uniqid('container_', true));

		return new $class();
	}

	/**
	 * @param array<string, mixed> $config
	 * @param list<class-string> $services
	 */
	private function createContainerWithServices(array $services, array $config = []): Container
	{
		$tempDir = sys_get_temp_dir() . '/nette-test-' . uniqid('', true);
		@mkdir($tempDir, 0o777, true); // @phpcs:ignore

		$loader = new ContainerLoader($tempDir, true);
		// phpcs:ignore SlevomatCodingStandard.Functions.StaticClosure.ClosureNotStatic
		$callback = function (Compiler $compiler) use ($config, $services): void {
			$compiler->addExtension('api', new ApiExtension());
			$serviceDefinitions = [];
			foreach ($services as $service) {
				$serviceDefinitions[] = $service;
			}

			$compiler->addConfig([
				'parameters' => [
					'productionMode' => false,
				],
				'api' => $config,
				'services' => $serviceDefinitions,
			]);
		};
		/** @var class-string<Container> $class */
		$class = $loader->load($callback, uniqid('container_', true));

		return new $class();
	}

}

// Test fixtures

final class SerializerHardeningDto
{

	#[MapProperty(path: 'address.city')]
	public string $city = 'Prague';

	private string $secret = 's';

	public function getSecret(): string
	{
		return $this->secret;
	}

}

#[MiddlewarePriority(300)]
final class ReferencePriorityMiddleware implements Middleware
{

	public function __invoke(ApiRequest $request, ApiResponse $response, callable $next): ApiResponse
	{
		return $next($request, $response);
	}

}

#[MiddlewarePriority(450)]
final class LatePriorityMiddleware implements Middleware
{

	public function __construct(public int $limit = 0)
	{
	}

	public function __invoke(ApiRequest $request, ApiResponse $response, callable $next): ApiResponse
	{
		return $next($request, $response);
	}

}

final class TestTraceIdProvider implements TraceIdProvider
{

	public function get(): string|null
	{
		return 'test-trace-id';
	}

}

final class TestErrorContextFilter implements ErrorContextFilter
{

	public function filter(array $context): array
	{
		unset($context['strip_me']);

		return $context;
	}

}

final class TestErrorResponseTransformer implements ErrorResponseTransformer
{

	public function transform(array $data, Throwable $error): array
	{
		$data['support'] = 'https://support.example.com';

		return $data;
	}

}
