<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Defines an alias path for an endpoint.
 *
 * Use this attribute to make an endpoint available at multiple URLs.
 * The alias endpoint will have identical behavior to the original.
 *
 * Example:
 * ```php
 * #[Get(path: '/employees/{id}')]
 * #[Alias('/contacts/{id}')]
 * #[Alias('/people/{id}')]
 * public function get(ApiRequest $request): EmployeeDto
 * ```
 *
 * This creates three endpoints all pointing to the same handler:
 * - GET /employees/{id}
 * - GET /contacts/{id}
 * - GET /people/{id}
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Alias
{

	public function __construct(public string $path)
	{
	}

}
