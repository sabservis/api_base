<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Path parameter attribute. Path parameters are always required.
 *
 * Example:
 * ```php
 * #[PathParameter(name: 'id', type: 'int', description: 'User ID')]
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class PathParameter extends RequestParameter
{

	public function __construct(
		string|null $name = null,
		string $type = 'string',
		string|null $description = null,
		bool $deprecated = false,
		string|null $format = null,
		mixed $example = null,
		Schema|null $schema = null,
	)
	{
		parent::__construct(
			name: $name,
			in: self::InPath,
			type: $type,
			description: $description,
			required: true,
			deprecated: $deprecated,
			allowEmptyValue: false,
			format: $format,
			example: $example,
			schema: $schema,
		);
	}

}
