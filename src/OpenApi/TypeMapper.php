<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi;

use function in_array;
use function is_string;
use function ltrim;

final class TypeMapper
{

	/**
	 * @return array{type: string, format?: string}
	 */
	public static function phpToOpenApi(string $phpType): array
	{
		// Normalize: remove leading backslash
		$phpType = ltrim($phpType, '\\');

		return match ($phpType) {
			'int', 'integer' => ['type' => 'integer'],
			'float', 'double' => ['type' => 'number', 'format' => 'double'],
			'bool', 'boolean' => ['type' => 'boolean'],
			'string' => ['type' => 'string'],
			'array' => ['type' => 'array'],
			'DateTimeInterface', 'DateTime', 'DateTimeImmutable' => ['type' => 'string', 'format' => 'date-time'],
			'Date' => ['type' => 'string', 'format' => 'date'],
			default => ['type' => 'object'],
		};
	}

	public static function isScalarType(string $type): bool
	{
		return in_array(
			$type,
			['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'array', 'mixed'],
			true,
		);
	}

	public static function isDateType(string $type): bool
	{
		// Normalize: remove leading backslash
		$type = ltrim($type, '\\');

		return in_array($type, ['DateTimeInterface', 'DateTime', 'DateTimeImmutable', 'Date'], true);
	}

	/**
	 * Resolve OpenAPI type shorthands like 'date' and 'date-time' to proper type + format.
	 *
	 * @param string|array<string> $type
	 * @return array{type: string, format: string}|null Null if not a shorthand
	 */
	public static function resolveOpenApiTypeShorthand(string|array $type): array|null
	{
		if (!is_string($type)) {
			return null;
		}

		return match ($type) {
			'date' => ['type' => 'string', 'format' => 'date'],
			'date-time', 'datetime' => ['type' => 'string', 'format' => 'date-time'],
			default => null,
		};
	}

}
