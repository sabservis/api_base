<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

/**
 * Centralized JSON processing limits.
 *
 * Used by both ApiRequest and serializers to ensure consistent security limits.
 */
final class JsonLimits
{

	/**
	 * Default JSON decoding depth limit.
	 *
	 * Provides protection against stack overflow attacks with deeply nested JSON.
	 * 64 levels is sufficient for most real-world APIs while preventing attacks.
	 */
	public const DEFAULT_DEPTH = 64;

}
