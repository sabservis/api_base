<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use function preg_match;
use function preg_replace;
use function preg_replace_callback;

final class Regex
{

	/**
	 * @param 0|256|512|768 $flags
	 */
	public static function match(
		string $subject,
		string $pattern,
		int $flags = 0,
	): mixed
	{
		$ret = preg_match($pattern, $subject, $m, $flags);

		return $ret === 1 ? $m : null;
	}

	/**
	 * @param string|array<string> $replacement
	 */
	public static function replace(
		string $subject,
		string $pattern,
		string|array $replacement,
		int $limit = -1,
	): string|null
	{
		return preg_replace($pattern, $replacement, $subject, $limit);
	}

	public static function replaceCallback(
		string $subject,
		string $pattern,
		callable $callback,
		int $limit = -1,
	): string|null
	{
		return preg_replace_callback($pattern, $callback, $subject, $limit);
	}

}
