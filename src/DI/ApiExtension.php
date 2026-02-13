<?php declare(strict_types = 1);

namespace Sabservis\Api\DI;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Pocta\DataMapper\Validation\Validator as DataMapperValidator;
use Pocta\DataMapper\Validation\ValidatorResolverInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Sabservis\Api\Application;
use Sabservis\Api\Attribute;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\ErrorHandler\PsrLogErrorHandler;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Mapping\MultipartEntityHydrator;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\ContainerValidatorResolver;
use Sabservis\Api\Mapping\Validator\DataMapperEntityValidator;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Middleware\ApiMiddleware;
use Sabservis\Api\Middleware\CORSMiddleware;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\Security\AuthorizationChecker;
use Sabservis\Api\Utils\ChainBuilder;
use stdClass;
use function assert;
use function class_exists;
use function is_object;
use function is_string;
use function sprintf;
use function uasort;

/** @method stdClass getConfig() */
final class ApiExtension extends Nette\DI\CompilerExtension
{

	public function getConfigSchema(): Nette\Schema\Schema
	{
		$parameters = $this->getContainerBuilder()->parameters;

		return Nette\Schema\Expect::structure([
			'debug' => Nette\Schema\Expect::bool($parameters['productionMode'] === false),
			'trustedProxies' => Nette\Schema\Expect::arrayOf('string')->default([]),
			'maxRequestBodySize' => Nette\Schema\Expect::int()->nullable()->default(null),
			'middlewares' => Nette\Schema\Expect::arrayOf(
				Nette\Schema\Expect::anyOf(
					Nette\Schema\Expect::string(),
					Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
					Nette\Schema\Expect::structure([
						'class' => Nette\Schema\Expect::anyOf(
							Nette\Schema\Expect::string(),
							Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
						),
						'priority' => Nette\Schema\Expect::int(10),
					]),
				),
			),
			'errorHandler' => Expect::type('string|array|' . Statement::class)->default(SimpleErrorHandler::class),
			'resources' => Nette\Schema\Expect::structure([
				'excludes' => Nette\Schema\Expect::arrayOf('string'),
				'paths' => Nette\Schema\Expect::arrayOf('string'),
			]),
			'serializer' => Expect::type('string|array|' . Statement::class)->default(DataMapperSerializer::class),
			'validator' => Expect::type('string|array|' . Statement::class)
				->nullable()
				->default(DataMapperEntityValidator::class),
			'validatorResolver' => Expect::type('string|array|' . Statement::class)
				->nullable()
				->default(ContainerValidatorResolver::class),
			'router' => Nette\Schema\Expect::structure([
				'basePath' => Nette\Schema\Expect::string()->nullable()->default(null),
				'cache' => Nette\Schema\Expect::anyOf(
					Nette\Schema\Expect::string(),
					Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
				)->nullable()->default(null),
			]),
			'cors' => Nette\Schema\Expect::structure([
				'enabled' => Nette\Schema\Expect::bool(false),
				'allowedOrigins' => Nette\Schema\Expect::arrayOf('string')->default([]),
				'allowedMethods' => Nette\Schema\Expect::arrayOf('string')->default(
					['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
				),
				'allowedHeaders' => Nette\Schema\Expect::arrayOf('string')->default(
					['Content-Type', 'Authorization', 'X-Requested-With'],
				),
				'allowCredentials' => Nette\Schema\Expect::bool(false),
				'maxAge' => Nette\Schema\Expect::int(3_600),
				'exposedHeaders' => Nette\Schema\Expect::arrayOf('string')->default([]),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		// Process API extensions
		$this->registerCoreServices($builder);
		$this->registerCoreMappingServices($builder);
		$this->registerCorsMiddleware($builder);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Validate middleware configuration
		$this->validateMiddlewareConfiguration($config);

		// Schema
		$builder->addDefinition($this->prefix('schema.hydrator'))
			->setFactory(ArrayHydrator::class);

		$schemaDefinition = $builder->getDefinition($this->prefix('schema'));
		assert($schemaDefinition instanceof Nette\DI\Definitions\ServiceDefinition);
		$schemaDefinition->setFactory(
			'@' . $this->prefix('schema.hydrator') . '::hydrate',
			[$this->compileSchema()],
		);

		// Error handler
		$errorHandlerDefinition = $builder->getDefinition($this->prefix('errorHandler'));
		assert($errorHandlerDefinition instanceof Nette\DI\Definitions\ServiceDefinition);

		// Set error handler to PsrErrorHandler if logger is available and user didn't change logger himself
		if ($errorHandlerDefinition->getFactory()->getEntity() === SimpleErrorHandler::class) {
			try {
				$loggerDefinition = $builder->getDefinitionByType(LoggerInterface::class);
				$errorHandlerDefinition->setFactory(PsrLogErrorHandler::class, [$loggerDefinition]);
			} catch (Nette\DI\MissingServiceException) {
				// No need to handle
			}
		}

		// Add CORS middleware to chain first (needs to run before other middlewares for preflight)
		$this->compileCorsMiddleware();

		// Register all middlewares
		$this->compileDefinedMiddlewares();
	}

	/**
	 * Validate middleware configuration for common issues.
	 *
	 * @throws RuntimeStateException When configuration has critical issues
	 */
	private function validateMiddlewareConfiguration(stdClass $config): void
	{
		// Validate CORS configuration
		if ($config->cors->enabled && $config->cors->allowCredentials) {
			if ($config->cors->allowedOrigins === []) {
				throw new RuntimeStateException(
					'CORS: allowCredentials requires explicit allowedOrigins. ' .
					'Using credentials with wildcard origin is a security vulnerability.',
				);
			}
		}

		// Validate trustedProxies with maxRequestBodySize
		// (Without trustedProxies, X-Forwarded-* headers are ignored which is correct behavior)
	}

	/**
	 * @return array<mixed>
	 */
	protected function compileSchema(): array
	{
		$loader = new OpenApiAttributeLoader($this->getContainerBuilder());

		return $loader->load();
	}

	private function compileCorsMiddleware(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		if ($config->cors->enabled !== true) {
			return;
		}

		$chain = $builder->getDefinition($this->prefix('middleware.chain'));
		assert($chain instanceof Nette\DI\Definitions\ServiceDefinition);

		$corsMiddleware = $builder->getDefinition($this->prefix('middleware.cors'));
		$chain->addSetup('add', [$corsMiddleware]);
	}

	private function compileDefinedMiddlewares(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Obtain middleware chain builder
		$chain = $builder->getDefinition($this->prefix('middleware.chain'));
		assert($chain instanceof Nette\DI\Definitions\ServiceDefinition);

		// Add middleware services to chain
		$counter = 0;

		$definitions = [];

		/** @var object{class: string|Nette\DI\Definitions\Statement|null, priority: int|null}|string $service */
		foreach ($config->middlewares as $service) {
			$priority = null;

			if (is_object($service) && $service->class !== null) {
				$priority = $service->priority;
				$service = $service->class;
			}

			if (!($service instanceof Nette\DI\Definitions\Statement || is_string($service))) {
				throw new RuntimeStateException('Unsupported middleware definition');
			}

			$middlewareDef = $builder
				->addDefinition($this->prefix(sprintf('middleware.%s', $counter)))
				->setFactory($service);

			if ($priority === null && is_string($service) && class_exists($service)) {
				/** @var ReflectionClass<object> $reflectionClass */
				$reflectionClass = new ReflectionClass($service);

				foreach ($reflectionClass->getAttributes() as $attribute) {
					$attributeInstance = $attribute->newInstance();

					if (!($attributeInstance instanceof Attribute\Core\MiddlewarePriority)) {
						continue;
					}

					$priority = $attributeInstance->level;

					break;
				}
			}

			if ($priority !== null) {
				$middlewareDef->addTag('priority', $priority);
			}

			$definitions[] = [
				'definition' => $middlewareDef,
				'priority' => $priority ?? 10,
			];

			$counter++;
		}

		// Sort by priority
		uasort($definitions, static function (array $a, array $b) {
			$p1 = $a['priority'];
			$p2 = $b['priority'];

			if ($p1 === $p2) {
				return 0;
			}

			return $p1 < $p2 ? -1 : 1;
		});

		foreach ($definitions as $definition) {
			$chain->addSetup('add', [$definition['definition']]);
		}

		// General API middleware
		$apiMiddlewareDef = $builder->addDefinition($this->prefix('middleware.api'))
			->setFactory(ApiMiddleware::class);
		$chain->addSetup('add', [$apiMiddlewareDef]);
	}

	private function registerCoreServices(Nette\DI\ContainerBuilder $builder): void
	{
		$config = $this->getConfig();

		$builder->addDefinition($this->prefix('middleware.chain'))
			->setAutowired(false)
			->setFactory(ChainBuilder::class);

		$builder->addDefinition($this->prefix('dispatcher'))
			->setFactory(ApiDispatcher::class);

		$builder->addDefinition($this->prefix('authorization.checker'))
			->setFactory(AuthorizationChecker::class);

		$builder->addDefinition($this->prefix('errorHandler'))
			->setFactory($config->errorHandler)
			->setType(ErrorHandler::class)
			->addSetup('setDebugMode', [
				$config->debug,
			]);

		$applicationDef = $builder->addDefinition($this->prefix('application'))
			->setFactory(Application\ApiApplication::class)
			->setArguments([
				new Nette\DI\Definitions\Statement('@' . $this->prefix('middleware.chain') . '::create'),
				'@' . $this->prefix('errorHandler'),
			])
			->addSetup('setCatchExceptions', [!$config->debug]);

		if ($config->trustedProxies !== []) {
			$applicationDef->addSetup('setTrustedProxies', [$config->trustedProxies]);
		}

		if ($config->maxRequestBodySize !== null) {
			$applicationDef->addSetup('setMaxRequestBodySize', [$config->maxRequestBodySize]);
		}

		$builder->addDefinition($this->prefix('router'))
			->setFactory(Router\Router::class, [
				'cache' => $config->router->cache,
			])
			->addSetup('setBasePath', [
				$config->router->basePath ?? null,
			]);

		$builder->addDefinition($this->prefix('handler'))
			->setFactory(ServiceHandler::class, [
				'serializer' => '@' . $this->prefix('request.entity.serializer'),
				'validator' => $config->validator !== null ? '@' . $this->prefix('request.entity.validator') : null,
			]);

		$builder->addDefinition($this->prefix('schema'))
			->setFactory(Schema::class);
	}

	private function registerCoreMappingServices(Nette\DI\ContainerBuilder $builder): void
	{
		$config = $this->getConfig();

		$builder->addDefinition($this->prefix('request.parameters.mapping'))
			->setFactory(RequestParameterMapping::class);

		$builder->addDefinition($this->prefix('mapping.multipartHydrator'))
			->setFactory(MultipartEntityHydrator::class);

		if ($config->validatorResolver !== null) {
			$builder->addDefinition($this->prefix('mapping.validatorResolver'))
				->setType(ValidatorResolverInterface::class)
				->setFactory($config->validatorResolver);
		}

		if ($config->validator !== null) {
			$validatorArgs = $config->validatorResolver !== null
				? ['validatorResolver' => '@' . $this->prefix('mapping.validatorResolver')]
				: [];

			$builder->addDefinition($this->prefix('mapping.datamapper.validator'))
				->setFactory(DataMapperValidator::class, $validatorArgs);

			$builder->addDefinition($this->prefix('request.entity.validator'))
				->setType(EntityValidator::class)
				->setFactory($config->validator);
		}

		$builder->addDefinition($this->prefix('request.entity.serializer'))
			->setType(EntitySerializer::class)
			->setFactory($config->serializer);
	}

	private function registerCorsMiddleware(Nette\DI\ContainerBuilder $builder): void
	{
		$config = $this->getConfig();

		if ($config->cors->enabled !== true) {
			return;
		}

		$builder->addDefinition($this->prefix('middleware.cors'))
			->setFactory(CORSMiddleware::class, [
				'allowedOrigins' => $config->cors->allowedOrigins,
				'allowedMethods' => $config->cors->allowedMethods,
				'allowedHeaders' => $config->cors->allowedHeaders,
				'allowCredentials' => $config->cors->allowCredentials,
				'maxAge' => $config->cors->maxAge,
				'exposedHeaders' => $config->cors->exposedHeaders,
			]);
	}

}
