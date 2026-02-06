<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\Core;

use Attribute;
use Sabservis\Api\Security\Authorizer;

/**
 * Runtime authorization requirement for endpoint access.
 *
 * Authorizers are resolved from DI container by type.
 * Multiple Authorize attributes are combined with AND semantics.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Authorize
{

	/**
	 * @param class-string<Authorizer> $authorizer
	 */
	public function __construct(
		public string $activity,
		public string $authorizer,
	)
	{
	}

}
