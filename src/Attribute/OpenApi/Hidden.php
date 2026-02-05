<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Mark an endpoint as hidden from OpenAPI documentation.
 *
 * The endpoint will still be functional and routable, but it won't appear
 * in the generated OpenAPI specification.
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
#[Attribute(Attribute::TARGET_METHOD)]
final class Hidden
{

	public function __construct(public string|null $reason = null)
	{
	}

}
