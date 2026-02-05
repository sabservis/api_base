<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Header parameter attribute.
 *
 * Example:
 * ```php
 * #[HeaderParameter(name: 'X-API-Key', description: 'API key for authentication')]
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class HeaderParameter extends RequestParameter
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
			in: self::InHeader,
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
