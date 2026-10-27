<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Mark an endpoint or DTO property as hidden from OpenAPI documentation.
 *
 * The endpoint will still be functional and routable, but it won't appear
 * in the generated OpenAPI specification.
 *
 * On a DTO property it only hides the property from the schema - the property
 * is still hydrated from the request body. Do not rely on it to block input.
 *
 * Use cases:
 * - Internal/debug endpoints
 * - Deprecated endpoints not yet removed
 * - Admin-only endpoints hidden from public docs
 *
 * Example usage:
 * ```php
 * #[Get(path: '/internal/debug')]
 * #[Hidden]
 * public function debug(): array
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Hidden
{

	public function __construct(public string|null $reason = null)
	{
	}

}
