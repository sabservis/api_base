<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Attribute;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE | \Attribute::TARGET_PARAMETER)]
interface Normalizer
{

}
