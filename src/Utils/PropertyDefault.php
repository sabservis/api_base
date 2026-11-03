<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use ReflectionProperty;

/**
 * Default value of a property including promoted constructor properties.
 *
 * Promoted property has its default on the constructor parameter, ReflectionProperty::hasDefaultValue() is false.
 */
final class PropertyDefault
{

	/**
	 * @return array{mixed}|null Wrapped default (default may be null), null when the property has none
	 */
	public static function find(ReflectionProperty $property): array|null
	{
		if ($property->hasDefaultValue()) {
			return [$property->getDefaultValue()];
		}

		if (!$property->isPromoted()) {
			return null;
		}

		foreach ($property->getDeclaringClass()->getConstructor()?->getParameters() ?? [] as $parameter) {
			if ($parameter->getName() === $property->getName() && $parameter->isDefaultValueAvailable()) {
				return [$parameter->getDefaultValue()];
			}
		}

		return null;
	}

}
