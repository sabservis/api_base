<?php declare(strict_types = 1);

namespace Tests\Unit\DI;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Validation\ValidatorResolverInterface;
use ReflectionProperty;
use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\DI\ApiExtension;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\ErrorHandler\ErrorResponseBuilder;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\ContainerValidatorResolver;
use Sabservis\Api\Mapping\Validator\DataMapperEntityValidator;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Security\AuthorizationChecker;
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

final class TestTraceIdProvider implements \Sabservis\Api\ErrorHandler\TraceIdProvider
{

	public function get(): string|null
	{
		return 'test-trace-id';
	}

}

final class TestErrorContextFilter implements \Sabservis\Api\ErrorHandler\ErrorContextFilter
{

	public function filter(array $context): array
	{
		unset($context['strip_me']);

		return $context;
	}

}

final class TestErrorResponseTransformer implements \Sabservis\Api\ErrorHandler\ErrorResponseTransformer
{

	public function transform(array $data, \Throwable $error): array
	{
		$data['support'] = 'https://support.example.com';

		return $data;
	}

}
