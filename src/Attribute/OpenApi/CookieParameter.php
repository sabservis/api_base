<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Cookie parameter attribute.
 *
 * Example:
 * ```php
 * #[CookieParameter(name: 'session', description: 'Session cookie')]
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class CookieParameter extends RequestParameter
{

	public function __construct(
		string|null $name = null,
		string $type = 'string',
		string|null $description = null,
		bool|null $required = null,
		bool $deprecated = false,
		string|null $format = null,
		mixed $example = null,
		Schema|null $schema = null,
	)
	{
		parent::__construct(
			name: $name,
			in: self::InCookie,
			type: $type,
			description: $description,
			required: $required,
			deprecated: $deprecated,
			format: $format,
			example: $example,
			schema: $schema,
		);
	}

}
