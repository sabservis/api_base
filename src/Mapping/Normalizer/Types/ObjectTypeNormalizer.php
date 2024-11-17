<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use Normalizer;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Attribute\Denormalizer as DenormalizerAttribute;
use Sabservis\Api\Mapping\Attribute\Normalizer as NormalizerAttribute;
use Sabservis\Api\Mapping\Attribute\ObjectArrayDenormalizer;
use Sabservis\Api\Mapping\Entity\Entity;
use Sabservis\Api\Mapping\Normalizer\NormalizationErrorCollection;
use Throwable;
use UnitEnum;
use function array_key_exists;
use function array_merge;
use function array_pop;
use function array_unique;
use function assert;
use function count;
use function implode;
use function is_array;
use function is_object;
use function is_subclass_of;

class ObjectTypeNormalizer extends AbstractTypeNormalizer
{

	private const UNDEFINED = '__undefined_property__';

	private NormalizationErrorCollection|null $errorCollection = null;

	/** @var array<string> */
	private array $path = [];

	public function setErrorCollection(NormalizationErrorCollection $errorCollection): void
	{
		$this->errorCollection = $errorCollection;
	}

	/**
	 * @template T of object
	 * @param array<string, mixed> $value
	 * @param class-string<T>|null $dtoClass
	 */
	public function denormalize(mixed $value, string|null $dtoClass = null): object|null
	{
		if ($dtoClass === null) {
			throw new InvalidArgumentException('DTO class must be provided.');
		}

		$reflectionClass = new ReflectionClass($dtoClass);
		$constructor = $reflectionClass->getConstructor();
		$constructorArgs = [];
		$remainingData = $value;

		if ($constructor !== null) {
			try {
				$constructorArgs = $this->resolveConstructorArgs($constructor, $value, $remainingData);
			} catch (InvalidArgumentException) {
				return null;
			}
		}

		$dto = $reflectionClass->newInstanceArgs($constructorArgs);
		$this->setProperties($reflectionClass, $dto, $remainingData);

		return $dto;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function normalize(mixed $value): array
	{
		$dto = $value;

		$data = [];
		$reflectionClass = new ReflectionClass($dto);

		foreach ($this->getClassProperties($reflectionClass) as $property) {
			if (!$property->isInitialized($dto)) {
				if ($this->isNullable($property)) {
					$data[$property->getName()] = null;
				}

				continue;
			}

			$propertyValue = $property->getValue($dto);

			foreach ($property->getAttributes(Normalizer::class) as $attribute) {
				$denormalizerInstance = $attribute->newInstance();
				$typeReflection = $property->getType();
				assert($typeReflection instanceof ReflectionNamedType);
				$type = $attribute->getArguments()['type'] ?? $typeReflection->getName();
				$params = $attribute->getArguments();

				// @phpstan-ignore-next-line
				$propertyValue = $denormalizerInstance->normalize($propertyValue, $type, $params);
			}

			if ($propertyValue === null && !$this->isNullable($property)) {
				continue;
			}

			$data[$property->getName()] = $this->normalizeProperty($propertyValue, $property);
		}

		return $data;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return [Entity::class, 'object'];
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $remainingData
	 * @return array<int, mixed>
	 */
	private function resolveConstructorArgs(ReflectionMethod $constructor, array &$data, array &$remainingData): array
	{
		$constructorArgs = [];
		$numberOfRequiredParams = 0;

		foreach ($constructor->getParameters() as $param) {
			if (!$param->isOptional()) {
				$numberOfRequiredParams++;
			}

			$paramName = $param->getName();
			$this->path[] = $paramName;

			try {
				if (array_key_exists($paramName, $data)) {
					$constructorArgs[] = $this->denormalizeProperty($data[$paramName], $param);
					unset($remainingData[$paramName]);
				} elseif ($param->isDefaultValueAvailable()) {
					$constructorArgs[] = $param->getDefaultValue();
				} elseif ($param->allowsNull()) {
					$constructorArgs[] = null;
				} else {
					throw new InvalidArgumentException();
				}
			} catch (InvalidArgumentTypeException | InvalidArgumentException) {
				$this->errorCollection?->addError(implode('.', $this->path), 'Missing required field');
			} finally {
				array_pop($this->path);
			}
		}

		if (count($constructorArgs) < $numberOfRequiredParams) {
			throw new InvalidArgumentException('Error during constructor argument resolution');
		}

		return $constructorArgs;
	}

	/**
	 * @param ReflectionClass<object> $reflectionClass
	 * @param array<string, mixed> $remainingData
	 */
	private function setProperties(ReflectionClass $reflectionClass, object $dto, array &$remainingData): void
	{
		foreach ($this->getClassProperties($reflectionClass) as $property) {
			$propertyName = $property->getName();
			$this->path[] = $propertyName;

			if (!array_key_exists($propertyName, $remainingData)) {
				if ($this->isNullable($property)) {
					$property->setValue($dto, null);
				} elseif ($property->hasDefaultValue()) {
					$remainingData[$propertyName] = $property->getDefaultValue();
				} else {
					$this->errorCollection?->addError(implode('.', $this->path), 'Missing required field');
				}

				array_pop($this->path);

				continue;
			}

			try {
				$propertyValue = $this->denormalizeProperty($remainingData[$propertyName], $property);

				if ($propertyValue !== self::UNDEFINED) {
					$property->setValue($dto, $propertyValue);
				}
			} catch (InvalidArgumentException | InvalidArgumentTypeException $e) {
				$this->errorCollection?->addError(implode('.', $this->path), $e->getMessage());
			} catch (Throwable) {
				$this->errorCollection?->addError(
					implode('.', $this->path),
					'Error during property denormalization. Is the value correct?',
				);
			} finally {
				array_pop($this->path);
			}
		}
	}

	private function isNullable(ReflectionProperty|ReflectionParameter $property): bool
	{
		return $property->getType()?->allowsNull() ?? false;
	}

	private function denormalizeProperty(mixed $value, ReflectionProperty|ReflectionParameter $propertyOrParam): mixed
	{
		$type = $propertyOrParam->getType();
		assert($type instanceof ReflectionNamedType);

		try {
			foreach ($propertyOrParam->getAttributes() as $attribute) {
				$instance = $attribute->newInstance();

				if (!($instance instanceof ObjectArrayDenormalizer)) {
					continue;
				}

				$objectType = $attribute->getArguments()['targetType'] ?? null;

				if ($objectType) {
					return $this->denormalizeArray($value, $objectType);
				}
			}

			if ($type->isBuiltin() === true) {
				if ($this->isNullable($propertyOrParam) && $value === null) {
					return null;
				}

				return $this->denormalizeBuiltInType($value, $type->getName());
			}

			foreach ($propertyOrParam->getAttributes() as $attribute) {
				$instance = $attribute->newInstance();

				if ($instance instanceof DenormalizerAttribute) {
					$typeName = $attribute->getArguments()['type'] ?? $type->getName();
					$params = $attribute->getArguments();

					return $this->provider->findNormalizer($typeName)?->denormalize($value, ...$params);
				}
			}

			if ($value === null) {
				return null;
			}

			if (is_subclass_of($type->getName(), BackedEnum::class)) {
				// @phpstan-ignore-next-line
				return $this->provider->findNormalizer(BackedEnum::class)?->denormalize($value, $type->getName());
			}

			if (is_subclass_of($type->getName(), UnitEnum::class)) {
				// @phpstan-ignore-next-line
				return $this->provider->findNormalizer(UnitEnum::class)?->denormalize($value, $type->getName());
			}

			if (is_subclass_of($type->getName(), DateTimeInterface::class)) {
				return $this->provider->findNormalizer(DateTimeInterface::class)?->denormalize($value);
			}

			if (is_subclass_of($type->getName(), Entity::class)) {
				// @phpstan-ignore-next-line
				return $this->provider->findNormalizer('object')?->denormalize($value, $type->getName());
			}
		} catch (InvalidArgumentTypeException $e) {
			$this->errorCollection?->addError(implode('.', $this->path), $e->getMessage());
		} catch (Throwable) {
			$this->errorCollection?->addError(
				implode('.', $this->path),
				'Error during property denormalization. Is the value correct?',
			);
		}

		return self::UNDEFINED;
	}

	private function normalizeProperty(mixed $value, ReflectionProperty $property): mixed
	{
		if ($value === null) {
			return null;
		}

		$type = $property->getType();
		assert($type instanceof ReflectionNamedType);

		if (is_object($value)) {
			foreach ($property->getAttributes() as $attribute) {
				$instance = $attribute->newInstance();

				if ($instance instanceof NormalizerAttribute) {
					$customType = $attribute->getArguments()['type'] ?? $type->getName();
					$params = $attribute->getArguments();

					return $this->provider->findNormalizer($customType)?->normalize($value, ...$params);
				}
			}

			if ($value instanceof BackedEnum) {
				// @phpstan-ignore-next-line
				return $this->provider->findNormalizer(BackedEnum::class)?->normalize($value, $value::class);
			}

			if ($value instanceof UnitEnum) {
				// @phpstan-ignore-next-line
				return $this->provider->findNormalizer(UnitEnum::class)?->normalize($value, $value::class);
			}

			if ($value instanceof DateTimeInterface) {
				// @phpstan-ignore-next-line
				return $this->provider->findNormalizer(DateTimeInterface::class)?->normalize($value, null);
			}

			return $this->normalize($value);
		}

		if (is_array($value)) {
			return $this->normalizeArray($value, $property);
		}

		if ($type->isBuiltin() === true) {
			return $this->normalizeBuiltInType($value, $type->getName());
		}

		return $this->normalize($value);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param class-string $targetType
	 * @return array<int, mixed>
	 */
	private function denormalizeArray(array $data, string $targetType): array
	{
		$result = [];

		foreach ($data as $value) {
			$result[] = $value === null ? null : $this->denormalize($value, $targetType);
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<int, mixed>
	 */
	private function normalizeArray(array $data, string|ReflectionParameter|ReflectionProperty $targetType): array
	{
		$result = [];

		foreach ($data as $value) {
			if ($value === null) {
				$result[] = null;
			} elseif (is_object($value)) {
				$result[] = $this->normalize($value);
			} elseif (is_array($value)) {
				$result[] = $this->normalizeArray($value, $targetType);
			} else {
				$result[] = $this->normalizeBuiltInType($value, null);
			}
		}

		return $result;
	}

	private function denormalizeBuiltInType(mixed $value, string|null $type): mixed
	{
		if (!$type) {
			return $value;
		}

		$normalizer = $this->provider->findNormalizer($type);

		return $normalizer ? $normalizer->denormalize($value) : $value;
	}

	private function normalizeBuiltInType(mixed $value, string|null $type): mixed
	{
		if (!$type) {
			return $value;
		}

		$normalizer = $this->provider->findNormalizer($type);

		return $normalizer ? $normalizer->normalize($value) : $value;
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflectionClass
	 * @return array<ReflectionProperty>
	 */
	private function getClassProperties(ReflectionClass $reflectionClass): array
	{
		$properties = $reflectionClass->getProperties();
		$parentClass = $reflectionClass->getParentClass();

		return array_unique(
			$parentClass ? array_merge($properties, $this->getClassProperties($parentClass)) : $properties,
		);
	}

}
