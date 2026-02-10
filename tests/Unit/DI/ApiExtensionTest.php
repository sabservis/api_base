<?php declare(strict_types = 1);

namespace Tests\Unit\DI;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\DI\ApiExtension;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Validator\DataMapperEntityValidator;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Security\AuthorizationChecker;
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
	public function setsRouterBasePath(): void
	{
		$container = $this->createContainer([
			'router' => [
				'basePath' => '/api/v1',
			],
		]);

		self::assertTrue($container->hasService('api.router'));
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

}
