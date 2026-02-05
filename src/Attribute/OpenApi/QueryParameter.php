<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Query parameter attribute.
 *
 * Example:
 * ```php
 * #[QueryParameter(name: 'limit', type: 'int', description: 'Max items')]
 * #[QueryParameter(name: 'status', type: OrderStatus::class)]
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class QueryParameter extends RequestParameter
{

	public function __construct(
		string|null $name = null,
		string $type = 'string',
		string|null $description = null,
		bool|null $required = null,
		bool $deprecated = false,
		bool $allowEmptyValue = false,
		string|null $style = null,
		bool|null $explode = null,
		string|null $format = null,
		mixed $example = null,
		Schema|null $schema = null,
	)
	{
		parent::__construct(
			name: $name,
			in: self::InQuery,
			type: $type,
			description: $description,
			required: $required,
			deprecated: $deprecated,
			allowEmptyValue: $allowEmptyValue,
			format: $format,
			example: $example,
			style: $style,
			explode: $explode,
			schema: $schema,
		);
	}

}
