<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use Normalizer;
use ReflectionClass;
use ReflectionProperty;
use Sabservis\Api\Mapping\Attribute\Denormalizer;
use Sabservis\Api\Mapping\Attribute\ObjectArrayDenormalizer;
use Sabservis\Api\Mapping\Entity;
use UnitEnum;

class ObjectTypeNormalizer extends AbstractTypeNormalizer
{
	// Denormalizace - mapování pole do DTO objektu
	public function denormalize(array $data, string $dtoClass): object
	{
		$reflectionClass = new ReflectionClass($dtoClass);
		$constructor = $reflectionClass->getConstructor();
		$constructorArgs = [];
		$remainingData = $data;

		// Zpracování parametrů konstruktoru
		if ($constructor) {
			foreach ($constructor->getParameters() as $param) {
				$paramName = $param->getName();

				if (array_key_exists($paramName, $data)) {
					$constructorArgs[] = $this->denormalizeProperty($data[$paramName], $param);
					unset($remainingData[$paramName]);
				} elseif ($param->isDefaultValueAvailable()) {
					$constructorArgs[] = $param->getDefaultValue();
				} elseif ($param->allowsNull()) {
					$constructorArgs[] = null;
				} else {
					throw new InvalidArgumentException("Missing required parameter: $paramName");
				}
			}
		}

		// Vytvoření objektu s parametry pro konstruktor
		$dto = $reflectionClass->newInstanceArgs($constructorArgs);

		// Zpracování zbývajících properties, které nebyly zpracovány v konstruktoru
		foreach ($this->getClassProperties($reflectionClass) as $property) {
			$propertyName = $property->getName();

			if (!array_key_exists($propertyName, $remainingData)) {
				// Pokud hodnota neexistuje, nastavíme null, pokud property umožňuje null
				if ($this->isNullable($property)) {
					$property->setAccessible(true);
					$property->setValue($dto, null);
				}

				continue;
			}

			$property->setAccessible(true);
			$propertyValue = $this->denormalizeProperty($remainingData[$propertyName], $property);

			// Pokud je hodnota null, nastavíme ji pouze pokud je nullable
			if ($propertyValue === null && !$this->isNullable($property)) {
				continue;
			}

			$property->setValue($dto, $propertyValue);
		}

		return $dto;
	}

	public function normalize(object $dto): array
	{
		$data = [];
		$reflectionClass = new ReflectionClass($dto);

		foreach ($this->getClassProperties($reflectionClass) as $property) {
			$property->setAccessible(true);

			if (!$property->isInitialized($dto)) {
				if ($this->isNullable($property)) {
					$data[$property->getName()] = null;
				}

				continue;
			}

			$propertyValue = $property->getValue($dto);

			// Kontrola atributů a volání specifických normalizátorů
			foreach ($property->getAttributes(Normalizer::class) as $attribute) {
				$denormalizerInstance = $attribute->newInstance();
				$type = $attribute->getArguments()['type'] ?? $property->getType()->getName();
				$params = $attribute->getArguments(); // Předej parametry atributu

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

	private function isNullable(ReflectionProperty $property): bool
	{
		$type = $property->getType();

		return $type !== null && $type->allowsNull();
	}

	private function denormalizeProperty(mixed $value, $propertyOrParam): mixed
	{
		$type = $propertyOrParam->getType();

		// Projdi všechny atributy
		foreach ($propertyOrParam->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();

			if (!($instance instanceof ObjectArrayDenormalizer)) {
                continue;
            }

            $objectType = $attribute->getArguments()['targetType'] ?? null;

            if ($objectType) {
                // Denormalizace jako pole objektů
                return $this->denormalizeArray($value, $objectType);
            }
		}

		// Pokud nejsou žádné atributy nebo je typ zabudovaný, použij defaultní logiku
		if (!$type || $type->isBuiltin()) {
			return $this->denormalizeBuiltInType($value, $type ? $type->getName() : null);
		}

		// Projdi všechny atributy
		foreach ($propertyOrParam->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();

			// Pokud je instance Denormalizeru (interface)
			if ($instance instanceof Denormalizer) {
				$type = $attribute->getArguments()['type'] ?? $type->getName();
				$params = $attribute->getArguments(); // Vezmi argumenty z attributu

				// Najdi normalizer pro daný typ a použij ho s parametry z konstruktoru atributu
				return $this->provider->findNormalizer($type)->denormalize($value, ...$params);
			}
		}

		// Pokud hodnota je null, vrať null
		if ($value === null) {
			return null;
		}

		// Další standardní logika pro speciální typy
		if (is_subclass_of($type->getName(), BackedEnum::class)) {
			return $this->provider->findNormalizer(BackedEnum::class)->denormalize($value, $type->getName());
		}

		if (is_subclass_of($type->getName(), UnitEnum::class)) {
			return $this->provider->findNormalizer(UnitEnum::class)->denormalize($value, $type->getName());
		}

		if (is_subclass_of($type->getName(), \DateTimeInterface::class)) {
			return $this->provider->findNormalizer(DateTimeInterface::class)->denormalize($value);
		}

		if ($type->getName() === 'array') {
			return $this->denormalizeArray($value, $propertyOrParam);
		}

		return $this->denormalize($value, $type->getName());
	}

	private function normalizeProperty(mixed $value, $property): mixed
	{
		if ($value === null) {
			return null;
		}

		$type = $property->getType();

		// Pokud je hodnota objekt, zavolej jeho normalizaci
		if (is_object($value)) {
			// Projdi všechny atributy
			foreach ($property->getAttributes() as $attribute) {
				$instance = $attribute->newInstance();

				// Pokud je instance ObjectArrayDenormalizer
				if ($instance instanceof ObjectArrayDenormalizer) {
					$customType = $attribute->getArguments()['targetType'] ?? null;

					if ($customType) {
						// Normalizace jako pole objektů
						return $this->normalizeArray($value, $customType);
					}
				}

				// Pokud je instance Denormalizeru (interface)
				if ($instance instanceof Denormalizer) {
					$customType = $attribute->getArguments()['type'] ?? $type->getName();
					$params = $attribute->getArguments(); // Vezmi parametry z konstruktoru atributu

					// Najdi normalizer pro daný typ a použij ho
					return $this->provider->findNormalizer($customType)->normalize($value, ...$params);
				}
			}

			// Pokud je to instance BackedEnum, zavoláme normalizátor pro BackedEnum
			if ($value instanceof BackedEnum) {
				return $this->provider->findNormalizer(BackedEnum::class)->normalize($value, $value::class);
			}

			// Pokud je to instance UnitEnum, zavoláme normalizátor pro UnitEnum
			if ($value instanceof UnitEnum) {
				return $this->provider->findNormalizer(UnitEnum::class)->normalize($value, $value::class);
			}

			// Normalizuj standardní objekt
			return $this->normalize($value);
		}

		// Pokud je hodnota pole, zavolej metodu normalizeArray
		if (is_array($value)) {
			return $this->normalizeArray($value, $property);
		}

		// Pokud je vestavěný typ (string, int, atd.), normalizujeme vestavěné typy
		if (!$type || $type->isBuiltin()) {
			return $this->normalizeBuiltInType($value, $type ? $type->getName() : null);
		}

		return $this->normalize($value);
	}


	// Denormalizace pole
	private function denormalizeArray(array $data, $targetType): array
	{
		$result = [];

		foreach ($data as $value) {
			if ($value === null) {
				$result[] = null;
			} else {
				// Denormalizace jednotlivých položek v poli jako objektů zadaného typu
				$result[] = $this->denormalize($value, $targetType);
			}
		}

		return $result;
	}

	// Normalizace pole
	private function normalizeArray(array $data, $targetType): array
	{
		$result = [];

		foreach ($data as $value) {
			if ($value === null) {
				// Přidání null hodnoty do výsledného pole
				$result[] = null;
			} elseif (is_object($value)) {
				// Pokud je to objekt, zavolej jeho normalizaci
				$result[] = $this->normalize($value);
			} elseif (is_array($value)) {
				// Pokud je to vnořené pole, rekurzivně jej normalizuj
				$result[] = $this->normalizeArray($value, $targetType);
			} else {
				// Pokud je to vestavěný typ (např. string, int), přidej ho do výsledného pole
				$result[] = $this->normalizeBuiltInType($value, null);
			}
		}

		return $result;
	}

	// Normalizace/denormalizace vestavěných typů
	private function denormalizeBuiltInType(mixed $value, ?string $type): mixed
	{
		if (!$type) {
			return $value;
		}

		$normalizer = $this->provider->findNormalizer($type);

		return $normalizer ? $normalizer->denormalize($value) : $value;
	}

	private function normalizeBuiltInType(mixed $value, ?string $type): mixed
	{
		if (!$type) {
			return $value;
		}

		$normalizer = $this->provider->findNormalizer($type);

		return $normalizer ? $normalizer->normalize($value) : $value;
	}

	// Získání všech properties včetně těch zděděných tříd
	private function getClassProperties(ReflectionClass $reflectionClass): array
	{
		$properties = $reflectionClass->getProperties();
		$parentClass = $reflectionClass->getParentClass();

		return $parentClass ? array_merge($properties, $this->getClassProperties($parentClass)) : $properties;
	}
}
