<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use ReflectionClass;
use ReflectionParameter;
use Sabservis\Api\Mapping\Attribute\Denormalizer;
use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Types\TypeNormalizer;
use TypeError;

class EntityBuilder
{

	/**
	 * @var array<string, list<ReflectionParameter>>
	 */
	private static array $parametersCache = [];

	public function __construct(protected NormalizerProvider|null $normalizer)
	{
	}

	public function deserialize(string $targetClass, array $data): Entity
	{

		$constructorParameters = $this->getConstructorParameters($targetClass);

		$args = [];
		$used = [];

		foreach ($constructorParameters as $parameter) {
			if (!array_key_exists($parameter['name'], $data)) {
				continue;
			}

			$value = $data[$parameter['name']];

			$args[] = $this->denormalize($parameter, $value);
			$used[] = $parameter['name'];
		}

		/** @var AbstractEntity $inst */
		$inst = new $targetClass(...$args);

		// Fill properties with real data
		$properties = $inst->getProperties();

		foreach ($properties as $property) {
			if (!array_key_exists($property['name'], $data)) {
				continue;
			}

			// Skip already filled properties
			if (in_array($property['name'], $used, true)) {
				continue;
			}

			$value = $data[$property['name']];

			// Normalize & convert value (only not null values)
			if ($value !== null) {
				$value = $this->denormalize($property, $value);
			}

			// Fill single property
			try {
				$inst->{$property['name']} = $value;
			} catch (TypeError) {
				// do nothing, entity will be invalid if something is missing and ValidationException will be thrown
			}
		}

		return $inst;
	}

	public function serialize(AbstractEntity $entity): array
	{
		$output = [];

		$properties = $entity->getProperties();

		foreach ($properties as $property) {
			$value = $entity->{$property['name']};
			$type = $property['type'];

			$args = [];
			$output[$property['name']] = $value;

			// Has some attributes
			foreach ($property['attributes'] as $attribute) {
				$attrInst = $attribute->newInstance();

				if ($attrInst instanceof Denormalizer === false) {
					continue;
				}

				$objectVars = get_object_vars($attrInst);

				if ($objectVars['type'] !== null) {
					$type = $objectVars['type'];
					unset($objectVars['type']);
				}

				$args += $objectVars;
			}

			if (is_subclass_of($type, \BackedEnum::class)) {
				$type = \BackedEnum::class;
				$args[] = $property['type'];
			} elseif (is_subclass_of($type, \UnitEnum::class)) {
				$type = \UnitEnum::class;
				$args[] = $property['type'];
			} elseif (is_array($value)) {
				$output[$property['name']] = $this->iterateArrayToArray($value);
				continue;
			} elseif (is_subclass_of($type, AbstractEntity::class)) {
				$output[$property['name']] = $this->serialize($value);
				continue;
			}

			$normalizer = $type !== null ? $this->normalizer->findNormalizer($type) : null;
			$output[$property['name']] = $normalizer instanceof TypeNormalizer ? $normalizer->normalize($value, ...$args) : $value;
		}

		return $output;
	}

	public function denormalize(array $property, mixed $value): mixed
	{
		if ($value === null && $property['isNullable'] === false) {
			throw new TypeError("Property {$property['name']} is required");
		}

		if ($this->normalizer === null) {
			return $value;
		}

		$type = $property['type'];
		$args = [];

		foreach ($property['attributes'] as $attribute) {
			$attrInst = $attribute->newInstance();

			if ($attrInst instanceof Denormalizer === false) {
				continue;
			}

			$objectVars = get_object_vars($attrInst);

			if ($objectVars['type'] !== null) {
				$type = $objectVars['type'];
				unset($objectVars['type']);
			}

			$args += $objectVars;
		}

		if (is_subclass_of($type, \BackedEnum::class)) {
			$type = \BackedEnum::class;
			$args[] = $property['type'];
		} elseif (is_subclass_of($type, \UnitEnum::class)) {
			$type = \UnitEnum::class;
			$args[] = $property['type'];
		} elseif (is_subclass_of($type, AbstractEntity::class)) {
			return $this->denormalize($type, $value);
		}

		$normalizer = $type !== null ? $this->normalizer->findNormalizer($type) : null;

		return $normalizer instanceof TypeNormalizer ? $normalizer->denormalize($value, ...$args) : $value;
	}

	public function normalize(array $property, mixed $value): mixed
	{
		if ($this->normalizer === null) {
			return $value;
		}

		$type = $property['type'];
		$args = [];

		foreach ($property['attributes'] as $attribute) {
			$attrInst = $attribute->newInstance();

			if ($attrInst instanceof Denormalizer === false) {
				continue;
			}

			$objectVars = get_object_vars($attrInst);

			if ($objectVars['type'] !== null) {
				$type = $objectVars['type'];
				unset($objectVars['type']);
			}

			$args += $objectVars;
		}

		if (is_subclass_of($type, \BackedEnum::class)) {
			$type = \BackedEnum::class;
			$args[] = $property['type'];
		} elseif (is_subclass_of($type, \UnitEnum::class)) {
			$type = \UnitEnum::class;
			$args[] = $property['type'];
		} elseif (is_subclass_of($type, AbstractEntity::class)) {
			return $this->deserialize($type, $value);
		}

		$normalizer = $type !== null ? $this->normalizer->findNormalizer($type) : null;

		return $normalizer instanceof TypeNormalizer ? $normalizer->normalize($value, ...$args) : $value;
	}

	/**
	 * @template T of AbstractEntity
	 * @param class-string<T> $className
	 * @return list<array<mixed>>
	 *
	 * @throws \ReflectionException
	 */
	protected function getConstructorParameters(string $className): array
	{
		if (!isset(self::$parametersCache[$className])) {

			// Získání reflexe konstruktoru
			$reflectionClass = new ReflectionClass($className);
			$constructor = $reflectionClass->getConstructor();

			/** @var array<ReflectionParameter> $parameters */
			$parameters = $constructor ? $constructor->getParameters() : [];

			$classParameters = [];

			foreach ($parameters as $parameter) {
				$classParameters[$parameter->getName()] = [
					'attributes' => $parameter->getAttributes(),
					'defaultValue' => $parameter->isDefaultValueAvailable()
						? $parameter->getDefaultValue()
						: null,
					'isNullable' => $parameter->hasType() === false || $parameter->allowsNull(),
					'name' => $parameter->getName(),
					'type' => $parameter->getType()?->getName() ?? null,
				];
			}

			self::$parametersCache[$className] = $classParameters;
		}

		return self::$parametersCache[$className];
	}

	/**
	 * @param array<mixed> $item
	 * @return array<mixed>
	 */
	private function iterateArrayToArray(array $item): array
	{
		$arr = [];

		foreach ($item as $subKey => $subitem) {
			if (is_array($subitem)) {
				$arr[$subKey] = $this->iterateArrayToArray($subitem);

				continue;
			}

			$arr[$subKey] = $subitem instanceof AbstractEntity ? $this->serialize($subitem) : $subitem;
		}

		return $arr;
	}

}
