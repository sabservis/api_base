<?php declare(strict_types = 1);

namespace Sabservis\Api\DI\Loader;

use Doctrine\Common\Annotations\Reader;
use Koriym\Attributes\AttributeReader;
use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionMethod;
use Sabservis\Api\Attribute as SOA;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\Builder\Controller\Controller;
use Sabservis\Api\Schema\Builder\Controller\Method as SchemaMethod;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\SchemaBuilder;
use Sabservis\Api\UI\Controller\Controller as ControllerInterface;
use function class_exists;
use function class_parents;
use function count;
use function is_array;
use function is_object;
use function is_string;
use function is_subclass_of;
use function mb_strtoupper;
use function property_exists;
use function sprintf;

class DoctrineAnnotationLoader extends AbstractContainerLoader
{

	private Reader|null $reader = null;

	/** @var array<mixed> */
	private array $meta
		= [
			'services' => [],
		];

	public function load(SchemaBuilder $builder): SchemaBuilder
	{
		// Find all controllers by type (interface, annotation)
		$controllers = $this->findControllers();

		// Iterate over all controllers
		foreach ($controllers as $def) {
			$type = $def->getType();

			if ($type === null) {
				throw new InvalidStateException(
					'Cannot analyse class with no type defined. Make sure all controllers have defined their class.',
				);
			}

			if (class_exists($type) === false) {
				throw new InvalidStateException(sprintf('Class %s does not exist', $type));
			}

			// Analyse all parent classes
			$class = $this->analyseClass($type);

			// Check if a controller or his abstract implements IController,
			// otherwise, skip this controller
			if (!$this->acceptController($class)) {
				continue;
			}

			// Create scheme endpoint
			$schemeController = $builder->addController($type);

			$this->parseControllerClassAnnotations($schemeController, $class);
			$this->parseControllerMethodsAnnotations($schemeController, $class);
		}

		return $builder;
	}

	/**
	 * @param string|class-string $class
	 * @return ReflectionClass<Controller>
	 */
	protected function analyseClass(string $class): ReflectionClass
	{
		// Analyse only new-ones
		if (isset($this->meta['services'][$class])) {
			return $this->meta['services'][$class]['reflection'];
		}

		if (!class_exists($class)) {
			throw new InvalidStateException(sprintf('Class %s does not exist', $class));
		}

		// Create reflection
		/** @var ReflectionClass<Controller> $classRef */
		$classRef = new ReflectionClass($class);

		// Index controller as service
		$this->meta['services'][$class] = [
			'parents' => [],
			'reflection' => $classRef,
		];

		// Get all parents
		/** @var array<string> $parents */
		$parents = class_parents($class);
		$reflections = [];

		// Iterate over all parents and analyse them
		foreach ($parents as $parentClass) {
			// Stop multiple analysing
			if (isset($this->meta['services'][$parentClass])) {
				// Just reference it in reflections
				$reflections[$parentClass] = $this->meta['services'][$parentClass]['reflection'];

				continue;
			}

			if (!class_exists($parentClass)) {
				continue;
			}

			// Create reflection for parent class
			$parentClassRf = new ReflectionClass($parentClass);
			$reflections[$parentClass] = $parentClassRf;

			// Index service
			$this->meta['services'][$parentClass] = [
				'parents' => [],
				'reflection' => $parentClassRf,
			];

			// Analyse parent (recursive)
			$this->analyseClass($parentClass);
		}

		// Append all parents to this service
		$this->meta['services'][$class]['parents'] = $reflections;

		return $classRef;
	}

	/**
	 * @param ReflectionClass<Controller> $class
	 */
	protected function acceptController(ReflectionClass $class): bool
	{
		return is_subclass_of($class->getName(), ControllerInterface::class);
	}

	/**
	 * @param ReflectionClass<Controller> $class
	 */
	protected function parseControllerClassAnnotations(
		Controller $controller,
		ReflectionClass $class,
	): void
	{
		// Read class annotations
		$annotations = $this->getReader()->getClassAnnotations($class);

		// Iterate over all class annotations in controller
		foreach ($annotations as $annotation) {
			// Parse @Tag ==================================
			if (!($annotation instanceof SOA\Tag)) {
				continue;
			}

			if ($annotation->name === Generator::UNDEFINED) {
				throw new InvalidStateException(sprintf('Tag in class %s has no name defined', $class->getName()));
			}

			$controller->addTag($annotation->name);
		}
	}

	/**
	 * @param ReflectionClass<Controller> $reflectionClass
	 */
	protected function parseControllerMethodsAnnotations(
		Controller $controller,
		ReflectionClass $reflectionClass,
	): void
	{
		// Iterate over all methods in class
		foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

			// Read method annotations
			/** @var array<SOA\Get|SOA\Post|SOA\Delete|SOA\Patch|SOA\Put|SOA\Options> $annotations */
			$annotations = $this->getReader()->getMethodAnnotations($method);

			// Skip if method has no @Path/@Method annotations
			if (count($annotations) <= 0) {
				continue;
			}

			// Append method to scheme
			$schemaMethod = $controller->addMethod($method->getName());

			// Iterate over all method annotations
			foreach ($annotations as $annotation) {
				if ($annotation instanceof SOA\RequestOperationAttribute) {
					if ($annotation->method !== Generator::UNDEFINED) {
						$schemaMethod->addHttpMethod(mb_strtoupper($annotation->method));
					}

					if ($annotation->path !== Generator::UNDEFINED) {
						$schemaMethod->setPath($annotation->path);
					}

					if ($annotation->tags !== Generator::UNDEFINED && is_array($annotation->tags)) {
						foreach ($annotation->tags as $tag) {
							$schemaMethod->addTag($tag);
						}
					}

					if ($annotation->operationId !== Generator::UNDEFINED) {
						$schemaMethod->setId($annotation->operationId);
					}

					if ($annotation->parameters !== Generator::UNDEFINED) {
						foreach ($annotation->parameters as $parameter) {
							$this->addOpenApiEndpointParameterToSchemaMethod($schemaMethod, $parameter);
						}
					}

					if ($annotation->requestBody !== null && is_object($annotation->requestBody)) {
						$hasValidRef = false;

						if (property_exists($annotation->requestBody, '_unmerged')) {
							foreach ($annotation->requestBody->_unmerged as $item) {
								if (!($item instanceof SOA\JsonContent)) {
									continue;
								}

								// Skip if ref is null or Generator::UNDEFINED
								if ($item->ref === null || $item->ref === Generator::UNDEFINED) {
									continue;
								}

								$requestBody = new EndpointRequestBody();
								$requestBody->setDescription(
									$annotation->requestBody->description !== Generator::UNDEFINED
										? $annotation->requestBody->description
										: '',
								);
								$requestBody->setEntity(is_object($item->ref) ? $item->ref::class : $item->ref);
								$requestBody->setRequired($annotation->requestBody->required !== Generator::UNDEFINED
									? $annotation->requestBody->required
									: false);
								$schemaMethod->setRequestBody($requestBody);
								$hasValidRef = true;
							}
						}

						// If no valid JsonContent with ref found, but requestBody exists, create empty requestBody
						if (!$hasValidRef) {
							$requestBody = new EndpointRequestBody();
							$requestBody->setDescription(
								$annotation->requestBody->description !== Generator::UNDEFINED
									? $annotation->requestBody->description
									: '',
							);
							$requestBody->setEntity(null);
							$requestBody->setRequired($annotation->requestBody->required !== Generator::UNDEFINED
								? $annotation->requestBody->required
								: false);
							$schemaMethod->setRequestBody($requestBody);
						}
					}

					continue;
				}

				// Parse Response
				if ($annotation instanceof SOA\Response) {
					if ($annotation->ref !== Generator::UNDEFINED && is_string($annotation->ref)) {
						$schemaMethod->addResponse($annotation->ref, $annotation->description);
					}

					continue;
				}

				// Parse RequestBody ================
				if ($annotation instanceof SOA\RequestBody) {
					$requestBody = new EndpointRequestBody();
					$requestBody->setDescription($annotation->description !== Generator::UNDEFINED
						? $annotation->description
						: '');
					$requestBody->setEntity($annotation->ref !== Generator::UNDEFINED
						? (is_object($annotation->ref) ? $annotation->ref::class : $annotation->ref)
						: null);
					$requestBody->setRequired($annotation->required !== Generator::UNDEFINED
						? $annotation->required
						: false);
					$schemaMethod->setRequestBody($requestBody);

					continue;
				}

				// Parse Tag
				if ($annotation instanceof SOA\Tag) {
					$schemaMethod->addTag($annotation->name, $annotation->getValue());

					continue;
				}

				// Parse Parameter
				if ($annotation instanceof SOA\Parameter) {
					$this->addOpenApiEndpointParameterToSchemaMethod($schemaMethod, $annotation);

					continue;
				}
			}
		}
	}

	protected function getReader(): Reader
	{
		if ($this->reader === null) {
			$this->reader = new AttributeReader();
		}

		return $this->reader;
	}

	private function addOpenApiEndpointParameterToSchemaMethod(
		SchemaMethod $schemaMethod,
		Parameter $requestParameter,
	): void
	{
		$type = 'string';

		if ($requestParameter->schema->type !== Generator::UNDEFINED) {
			$type = is_array($requestParameter->schema->type)
				? $requestParameter->schema->type[0]
				: $requestParameter->schema->type;

			$format = $requestParameter->schema->format !== Generator::UNDEFINED
				? $requestParameter->schema->format
				: null;

			if ($format === 'date' || $format === 'date-time') {
				$type = 'date';
			}
		} elseif ($requestParameter->schema->ref !== Generator::UNDEFINED) {
			if (is_string($requestParameter->schema->ref) && class_exists($requestParameter->schema->ref)) {
				$type = $requestParameter->schema->ref;
			}
		}

		$endpointParameter = $schemaMethod->addParameter($requestParameter->name, $type);

		if ($requestParameter->description !== Generator::UNDEFINED) {
			$endpointParameter->setDescription($requestParameter->description);
		}

		if ($requestParameter->in !== Generator::UNDEFINED) {
			$endpointParameter->setIn($requestParameter->in);
		}

		if ($requestParameter->required !== Generator::UNDEFINED) {
			$endpointParameter->setRequired($requestParameter->required);
		}

		if ($requestParameter->deprecated !== Generator::UNDEFINED) {
			$endpointParameter->setDeprecated($requestParameter->deprecated);
		}

		if ($requestParameter->allowEmptyValue === Generator::UNDEFINED) {
			return;
		}

		$endpointParameter->setAllowEmpty($requestParameter->allowEmptyValue);
	}

}
