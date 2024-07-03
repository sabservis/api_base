<?php declare(strict_types = 1);

namespace Sabservis\Api\DI;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Loaders\RobotLoader;
use Nette\Schema\Expect;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Sabservis\Api\Application\HttpApplication;
use Sabservis\Api\Attribute;
use Sabservis\Api\Decorator;
use Sabservis\Api\DI\Attribute\DiService;
use Sabservis\Api\DI\Loader\DoctrineAnnotationLoader;
use Sabservis\Api\Dispatcher\Dispatcher;
use Sabservis\Api\Dispatcher\JsonDispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\ErrorHandler\PsrLogErrorHandler;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\InvalidStateException;
use Sabservis\Api\Handler\Handler;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Mapping\Parameter;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Serializer\NullSerializer;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Mapping\Validator\NullValidator;
use Sabservis\Api\Middleware\ApiMiddleware;
use Sabservis\Api\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\SchemaBuilder;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\Schema\Serialization\ArraySerializator;
use Sabservis\Api\Utils\ChainBuilder;
use stdClass;
use function array_filter;
use function assert;
use function class_exists;
use function count;
use function is_string;
use function sprintf;
use function str_starts_with;
use function substr;
use function uasort;

/** @method stdClass getConfig() */
final class ApiExtension extends Nette\DI\CompilerExtension
{

	public const MiddlewareTag = 'middleware';

	public const DecoratorTag = 'api.decorator';

	/** @var array<class-string<Parameter\TypeMapper>> */
	private array $defaultTypes
		= [
			'array' => Parameter\ArrayTypeMapper::class,
			'boolean' => Parameter\BooleanTypeMapper::class,
			'integer' => Parameter\IntegerTypeMapper::class,
			'number' => Parameter\NumberTypeMapper::class,
			'string' => Parameter\StringTypeMapper::class,
		];

	/** @var array<int, string> */
	private array $discoveredServices = [];

	public function getConfigSchema(): Nette\Schema\Schema
	{
		$parameters = $this->getContainerBuilder()->parameters;

		return Nette\Schema\Expect::structure([
			'catchException' => Nette\Schema\Expect::bool(true),
			'debug' => Nette\Schema\Expect::bool($parameters['debugMode'] ?? false),
			'middlewares' => Nette\Schema\Expect::arrayOf(
				Nette\Schema\Expect::anyOf(
					Nette\Schema\Expect::string(),
					Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
				),
			),
			'resources' => Nette\Schema\Expect::structure([
				'paths' => Nette\Schema\Expect::arrayOf('string'),
				'excludes' => Nette\Schema\Expect::arrayOf('string'),
			]),
			'validator' => Expect::type('string|array|' . Statement::class)->default(NullValidator::class),
			'serializer' => Expect::type('string|array|' . Statement::class)->default(NullSerializer::class),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Process resources
		$this->discoveredServices = $this->findClassesForRegistration(
			$config->resources->paths,
			$config->resources->excludes,
		);

		// Process API extensions
		$this->registerMiddlewareServices($builder);
		$this->registerCoreServices($builder);
		$this->registerCoreDecoratorServices($builder);
		$this->registerCoreMappingServices($builder);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Register services of given resource
		$counter = 1;
		foreach ($this->discoveredServices as $class) {
			// Check already registered classes
			if ($builder->getByType($class) !== null) {
				continue;
			}

			$name = sprintf('service.%s', $counter++);
			$builder->addDefinition($this->prefix($name))
				->setFactory($class)
				->setType($class);
		}

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

		// Decorators
		$managerDefinition = $builder->getDefinition($this->prefix('decorator.manager'));
		assert($managerDefinition instanceof Nette\DI\Definitions\ServiceDefinition);

		$requestDecoratorDefinitions = $builder->findByType(Decorator\RequestDecorator::class);
		$requestDecoratorDefinitions = Helpers::sortByPriorityInTag(self::DecoratorTag, $requestDecoratorDefinitions);

		foreach ($requestDecoratorDefinitions as $decoratorDefinition) {
			$managerDefinition->addSetup('addRequestDecorator', [$decoratorDefinition]);
		}

		$responseDecoratorDefinitions = $builder->findByType(Decorator\ResponseDecorator::class);
		$responseDecoratorDefinitions = Helpers::sortByPriorityInTag(self::DecoratorTag, $responseDecoratorDefinitions);

		foreach ($responseDecoratorDefinitions as $decoratorDefinition) {
			$managerDefinition->addSetup('addResponseDecorator', [$decoratorDefinition]);
		}

		$errorDecoratorDefinitions = $builder->findByType(Decorator\ErrorDecorator::class);
		$errorDecoratorDefinitions = Helpers::sortByPriorityInTag(self::DecoratorTag, $errorDecoratorDefinitions);

		foreach ($errorDecoratorDefinitions as $decoratorDefinition) {
			$managerDefinition->addSetup('addErrorDecorator', [$decoratorDefinition]);
		}

		// Compile defined middlewares
		if ($config->middlewares !== []) {
			$this->compileDefinedMiddlewares();
		}

		// Compile tagged middlewares
		if ($builder->findByTag(self::MiddlewareTag) !== []) {
			$this->compileTaggedMiddlewares();

			return;
		}

		throw new InvalidStateException('There must be at least one middleware registered or added by tag.');
	}

	/**
	 * @return array<mixed>
	 */
	protected function compileSchema(): array
	{
		// Instance schema builder
		$builder = new SchemaBuilder();

		// Load schema
		$builder = $this->loadSchema($builder);

		// Validate schema TODO
		// $builder = $this->validateSchema($builder);

		// Convert schema to array (for DI)
		$generator = new ArraySerializator();

		return $generator->serialize($builder);
	}

	protected function loadSchema(SchemaBuilder $builder): SchemaBuilder
	{
		$loader = new DoctrineAnnotationLoader($this->getContainerBuilder());
		$builder = $loader->load($builder);

		return $builder;
	}

	/*
	protected function validateSchema(SchemaBuilder $builder): SchemaBuilder
	{
		$validations = $this->config->validations;

		$validator = new SchemaBuilderValidator();

		// Add all validators at compile-time

		// @var class-string<Validation> $validation
		foreach ($validations as $validation) {
			$validator->add(new $validation());
		}

		//      / @var ?CoreMappingPlugin $coreMappingPlugin /
		//      $coreMappingPlugin = $this->compiler->getPlugin(CoreMappingPlugin::getName());
		//      if ($coreMappingPlugin !== null) {
		//          $validator->add(new RequestParameterValidation($coreMappingPlugin->getAllowedTypes()));
		//      }

		// Validate schema
		$validator->validate($builder);

		return $builder;
	}
	*/

	private function compileDefinedMiddlewares(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Obtain middleware chain builder
		$chain = $builder->getDefinition($this->prefix('middleware.chain'));
		assert($chain instanceof Nette\DI\Definitions\ServiceDefinition);

		// Add middleware services to chain
		$counter = 0;

		foreach ($config->middlewares as $service) {
			if (is_string($service) && str_starts_with($service, '@')) {
				// Re-use existing service
				$middlewareDef = $builder->getDefinition(substr($service, 1));
			} elseif ($service instanceof Nette\DI\Definitions\Statement || is_string($service)) {
				// Create middleware as service
				$middlewareDef = $builder->addDefinition($this->prefix('middleware' . $counter++))
					->setFactory($service);
			} else {
				throw new InvalidStateException('Unsupported middleware definition');
			}

			// Append to chain of middlewares
			$chain->addSetup('add', [$middlewareDef]);
		}
	}

	private function compileTaggedMiddlewares(): void
	{
		$builder = $this->getContainerBuilder();

		$definitions = [];

		foreach ($builder->getDefinitions() as $definition) {

			$type = (string) $definition->getType();

			if (!class_exists($type)) {
				continue;
			}

			$reflectionClass = new ReflectionClass($type);

			foreach ($reflectionClass->getAttributes() as $attribute) {
				$attributeInstance = $attribute->newInstance();

				if (!($attributeInstance instanceof Attribute\Core\AsMiddleware)) {
					continue;
				}

				$definitions[$definition->getName()] = [
					'priority' => $attributeInstance->priority ?? 10,
				];
			}
		}

		// Ensure we have at least 1 service
		if (count($definitions) === 0) {
			throw new InvalidStateException('No middlewares');
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

		// Obtain middleware chain builder
		$chain = $builder->getDefinition($this->prefix('middleware.chain'));
		assert($chain instanceof Nette\DI\Definitions\ServiceDefinition);

		// Add middleware services to chain
		foreach ($definitions as $name => $tag) {
			// Append to chain of middlewares
			$chain->addSetup('add', [$builder->getDefinition($name)]);
		}
	}

	private function registerMiddlewareServices(Nette\DI\ContainerBuilder $builder): void
	{
		// Register middleware chain builder
		$chain = $builder->addDefinition($this->prefix('middleware.chain'))
			->setAutowired(false);
		$chain->setFactory(ChainBuilder::class);

		// General API middleware
		$builder->addDefinition($this->prefix('middleware.api'))
			->setFactory(ApiMiddleware::class)
			->addTag(self::MiddlewareTag);
	}

	private function registerCoreServices(Nette\DI\ContainerBuilder $builder): void
	{
		$catchException = !$this->getConfig()->debug || $this->getConfig()->catchException;

		$builder->addDefinition($this->prefix('dispatcher'))
			->setFactory(JsonDispatcher::class)
			->setType(Dispatcher::class);

		$builder->addDefinition($this->prefix('errorHandler'))
			->setFactory(SimpleErrorHandler::class)
			->setType(ErrorHandler::class)
			->addSetup('setCatchException', [$catchException]);

		$builder->addDefinition($this->prefix('application'))
			->setFactory(HttpApplication::class)
			->setArguments([new Nette\DI\Definitions\Statement('@' . $this->prefix('middleware.chain') . '::create')]);

		$builder->addDefinition($this->prefix('router'))
			->setType(Router\Router::class)
			->setFactory(Router\SimpleRouter::class);

		$builder->addDefinition($this->prefix('handler'))
			->setType(Handler::class)
			->setFactory(ServiceHandler::class);

		$builder->addDefinition($this->prefix('schema'))
			->setFactory(Schema::class);
	}

	private function registerCoreDecoratorServices(Nette\DI\ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('decorator.manager'))
			->setFactory(Decorator\DecoratorManager::class);
	}

	private function registerCoreMappingServices(Nette\DI\ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('decorator.request.parameters'))
			->setFactory(Decorator\RequestParametersDecorator::class);

		$parametersMapping = $builder->addDefinition($this->prefix('request.parameters.mapping'))
			->setFactory(RequestParameterMapping::class);

		foreach ($this->defaultTypes as $type => $mapper) {
			$parametersMapping->addSetup('addMapper', [$type, $mapper]);
		}

		$builder->addDefinition($this->prefix('request.entity.validator'))
			->setType(EntityValidator::class)
			->setFactory($this->getConfig()->validator);

		$builder->addDefinition($this->prefix('request.entity.serializer'))
			->setType(EntitySerializer::class)
			->setFactory($this->getConfig()->serializer);

	}

	/**
	 * @param array<string> $dirs
	 * @param array<string> $excludes
	 * @return array<string>
	 */
	protected function findClassesForRegistration(array $dirs, array $excludes = []): array
	{
		$loader = $this->createRobotLoader();
		$loader->addDirectory(...$dirs);
		$loader->rebuild();

		$indexed = $loader->getIndexedClasses();

		$classes = [];
		foreach ($indexed as $class => $file) {

			// Excluded namespace
			if (array_filter(
				$excludes,
				static fn (string $exclude): bool => str_starts_with($class, $exclude),
			) !== []) {
				continue;
			}

			// Skip not existing class
			if (!class_exists($class)) {
				continue;
			}

			// Detect by reflection
			$ct = new ReflectionClass($class);

			// Skip abstract
			if ($ct->isAbstract()) {
				continue;
			}

			foreach ($ct->getAttributes() as $attribute) {
				$attributeInstance = $attribute->newInstance();

				if (!($attributeInstance instanceof DiService)) {
					continue;
				}

				$classes[] = $class;
			}
		}

		return $classes;
	}

	protected function createRobotLoader(): RobotLoader
	{
		if (!class_exists(RobotLoader::class)) {
			throw new InvalidStateException('Install nette/robot-loader at first');
		}

		return new RobotLoader();
	}

}
