<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute;

use Attribute;
use Sabservis\Api\Trait\OpenApiAttributeTrait;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Delete extends \OpenApi\Annotations\Delete implements RequestOperationAttribute
{

	use OpenApiAttributeTrait;

}
