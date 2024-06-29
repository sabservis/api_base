<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Attribute;

use Attribute;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE | Attribute::TARGET_PARAMETER)]
class ObjectArrayDenormalizer implements Denormalizer
{

	public string $type = 'object';

	public function __construct(public string $targetType)
	{
	}

}
