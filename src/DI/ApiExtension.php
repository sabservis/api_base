<?php declare(strict_types = 1);

namespace Sabservis\Api\DI;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Sabservis\Api\Application\HttpApplication;
use Sabservis\Api\Attribute;
use Sabservis\Api\Decorator;
use Sabservis\Api\DI\Loader\DoctrineAnnotationLoader;
use Sabservis\Api\Dispatcher\Dispatcher;
use Sabservis\Api\Dispatcher\JsonDispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\ErrorHandler\PsrLogErrorHandler;
use Sabservis\Api\ErrorHandler\SimpleErrorHandler;
use Sabservis\Api\Exception\InvalidStateException;
use Sabservis\Api\Handler\Handler;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Processor\MainNormalizerProcessor;
use Sabservis\Api\Mapping\Normalizer\Processor\NormalizerProcessor;
use Sabservis\Api\Mapping\Normalizer\TypeNormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Types;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Serializer\JsonSerializer;
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
use function assert;
use function class_exists;
use function count;
use function is_object;
use function is_string;
use function sprintf;
use function uasort;

/** @method stdClass getConfig() */
final class ApiExtension extends Nette\DI\CompilerExtension
{

	public const DecoratorTag = 'api.decorator';

	/** @var array<int, class-string<Types\TypeNormalizer>> $normalizerTypes */
	private array $normalizerTypes
		= [
			Types\ArrayTypeNormalizer::class,
			Types\BackedEnumTypeNormalizer::class,
			Types\BooleanTypeNormalizer::class,
			Types\DateTimeNormalizer::class,
			Types\FloatTypeNormalizer::class,
			Types\IntegerTypeNormalizer::class,
			Types\ObjectTypeNormalizer::class,
			Types\StringTypeNormalizer::class,
			Types\UnitEnumTypeNormalizer::class,
		];

	public function getConfigSchema(): Nette\Schema\Schema
	{
		$parameters = $this->getContainerBuilder()->parameters;

		return Nette\Schema\Expect::structure([
			'debug' => Nette\Schema\Expect::bool($parameters['productionMode'] === false),
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
			'normalizer' => Nette\Schema\Expect::structure([
				'class' => Nette\Schema\Expect::anyOf(
					Nette\Schema\Expect::string(),
					Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
				)->default(MainNormalizerProcessor::class),
				'typeProvider' => Nette\Schema\Expect::structure([
					'class' => Nette\Schema\Expect::anyOf(
						Nette\Schema\Expect::string(),
						Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
					)->default(TypeNormalizerProvider::class),
					'types' => Nette\Schema\Expect::arrayOf('string')
						->default([]),
				]),
			]),
			'errorHandler' => Expect::type('string|array|' . Statement::class)->default(SimpleErrorHandler::class),
			'resources' => Nette\Schema\Expect::structure([
				'excludes' => Nette\Schema\Expect::arrayOf('string'),
				'paths' => Nette\Schema\Expect::arrayOf('string'),
			]),
			'serializer' => Expect::type('string|array|' . Statement::class)->default(JsonSerializer::class),
			'validator' => Expect::type('string|array|' . Statement::class)->default(NullValidator::class),
			'router' => Nette\Schema\Expect::structure([
				'basePath' => Nette\Schema\Expect::string()->nullable()->default(null),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		// Process API extensions
		$this->registerCoreServices($builder);
		$this->registerCoreDecoratorServices($builder);
		$this->registerCoreMappingServices($builder);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

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

		// > Request decorators
		$requestDecoratorDefinitions = $builder->findByType(Decorator\RequestDecorator::class);
		$requestDecoratorDefinitions = Helpers::sortByPriorityInTag(self::DecoratorTag, $requestDecoratorDefinitions);

		foreach ($requestDecoratorDefinitions as $decoratorDefinition) {
			$managerDefinition->addSetup('addRequestDecorator', [$decoratorDefinition]);
		}

		// > Response decorators
		$responseDecoratorDefinitions = $builder->findByType(Decorator\ResponseDecorator::class);
		$responseDecoratorDefinitions = Helpers::sortByPriorityInTag(self::DecoratorTag, $responseDecoratorDefinitions);

		foreach ($responseDecoratorDefinitions as $decoratorDefinition) {
			$managerDefinition->addSetup('addResponseDecorator', [$decoratorDefinition]);
		}

		// > Error Decorators
		$errorDecoratorDefinitions = $builder->findByType(Decorator\ErrorDecorator::class);
		$errorDecoratorDefinitions = Helpers::sortByPriorityInTag(self::DecoratorTag, $errorDecoratorDefinitions);

		foreach ($errorDecoratorDefinitions as $decoratorDefinition) {
			$managerDefinition->addSetup('addErrorDecorator', [$decoratorDefinition]);
		}

		// Register all middlewares
		$this->compileDefinedMiddlewares();
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

		// Convert schema to array (for DI)
		$generator = new ArraySerializator();

		return $generator->serialize($builder);
	}

	protected function loadSchema(SchemaBuilder $builder): SchemaBuilder
	{
		$loader = new DoctrineAnnotationLoader($this->getContainerBuilder());

		return $loader->load($builder);
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
				throw new InvalidStateException('Unsupported middleware definition');
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
		$builder->addDefinition($this->prefix('middleware.chain'))
			->setAutowired(false)
			->setFactory(ChainBuilder::class);

		$builder->addDefinition($this->prefix('dispatcher'))
			->setFactory(JsonDispatcher::class)
			->setType(Dispatcher::class);

		$builder->addDefinition($this->prefix('errorHandler'))
			->setFactory($this->getConfig()->errorHandler)
			->setType(ErrorHandler::class)
			->addSetup('setDebugMode', [
				$this->getConfig()->debug,
			]);

		$builder->addDefinition($this->prefix('application'))
			->setFactory(HttpApplication::class)
			->setArguments([new Nette\DI\Definitions\Statement('@' . $this->prefix('middleware.chain') . '::create')]);

		$builder->addDefinition($this->prefix('router'))
			->setType(Router\Router::class)
			->setFactory(Router\SimpleRouter::class)
			->addSetup('setBasePath', [
				$this->getConfig()->router->basePath ?? null,
			]);

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
		$config = $this->getConfig();

		$builder->addDefinition($this->prefix('decorator.request.parameters'))
			->setFactory(Decorator\RequestParametersDecorator::class);

		$builder->addDefinition($this->prefix('request.parameters.mapping'))
			->setFactory(RequestParameterMapping::class);

		$builder->addDefinition($this->prefix('request.entity.validator'))
			->setType(EntityValidator::class)
			->setFactory($config->validator);

		$typeProvider = $builder->addDefinition($this->prefix('request.entity.normalizer.typeProvider'))
			->setType(NormalizerProvider::class)
			->setFactory($config->normalizer->typeProvider->class);

		$normalizers = count($config->normalizer->typeProvider->types)
			? $config->normalizer->typeProvider->types
			: $this->normalizerTypes;
		foreach ($normalizers as $normalizer) {
			$typeProvider->addSetup('addNormalizer', [$normalizer]);
		}

		$builder->addDefinition($this->prefix('request.entity.normalizer'))
			->setType(NormalizerProcessor::class)
			->setFactory($config->normalizer->class);

		$builder->addDefinition($this->prefix('request.entity.serializer'))
			->setType(EntitySerializer::class)
			->setFactory($config->serializer);
	}

}
