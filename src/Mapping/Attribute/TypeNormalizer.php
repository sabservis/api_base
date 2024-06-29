<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Attribute;

use Attribute;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE | Attribute::TARGET_PARAMETER)]
class TypeNormalizer implements Denormalizer
{

	public function __construct(public string|null $type = null)
	{
	}

}
