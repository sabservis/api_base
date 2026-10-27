<?php declare(strict_types = 1);

namespace Sabservis\Api\Router;

use function md5;
use function preg_match;
use function substr;

/**
 * Maps path variable names ({user-id}) to valid PCRE group names.
 *
 * PCRE group names allow only [A-Za-z0-9_], must not start with a digit and have max 32 chars.
 * Valid names are kept as they are, others get a deterministic replacement - the router maps it back.
 */
final class PathVariable
{

	private const VALID_GROUP_NAME = '/^[A-Za-z_][A-Za-z0-9_]{0,31}\z/';

	public static function groupName(string $name): string
	{
		if (preg_match(self::VALID_GROUP_NAME, $name) === 1) {
			return $name;
		}

		return 'p' . substr(md5($name), 0, 16);
	}

}
