<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use function preg_replace;

/**
 * Utility for sanitizing HTTP header values.
 *
 * Prevents HTTP response splitting attacks by removing dangerous characters.
 */
final class HeaderSanitizer
{

	/**
	 * Sanitize header value to prevent HTTP response splitting attacks.
	 *
	 * Removes CR, LF, and null bytes which could be used for header injection.
	 * These characters can be used to inject additional headers or even
	 * a complete HTTP response body.
	 */
	public static function sanitize(string $value): string
	{
		return (string) preg_replace('/[\r\n\x00]/', '', $value);
	}

}
