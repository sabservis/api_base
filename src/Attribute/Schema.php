<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute;

use Attribute;
use OpenApi\Annotations;
use OpenApi\Generator;
use UnitEnum;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Schema extends Annotations\Schema
{

	/**
	 * @param string|non-empty-array<string>|null                           $type
	 * @param string|class-string|object|null                               $ref
	 * @param array<string>|null                                            $required
	 * @param array<Property>|null                                          $properties
	 * @param int|float                                                     $maximum
	 * @param int|float                                                     $minimum
	 * @param array<string|int|float|bool|UnitEnum|null>|class-string|null  $enum
	 * @param array<Examples>                                               $examples
	 * @param array<Schema|\OpenApi\Annotations\Schema>                     $allOf
	 * @param array<Schema|\OpenApi\Annotations\Schema>                     $anyOf
	 * @param array<Schema|\OpenApi\Annotations\Schema>                     $oneOf
	 * @param array<string,mixed>|null                                      $x
	 * @param array<Attachable>|null                                        $attachables
	 */
	public function __construct(
		// schema
		string|object|null $ref = null,
		string|null $schema = null,
		string|null $title = null,
		string|null $description = null,
		int|null $maxProperties = null,
		int|null $minProperties = null,
		array|null $required = null,
		array|null $properties = null,
		string|array|null $type = null,
		string|null $format = null,
		Items|null $items = null,
		string|null $collectionFormat = null,
		mixed $default = Generator::UNDEFINED,
		$maximum = null,
		bool|int|float|null $exclusiveMaximum = null,
		$minimum = null,
		bool|int|float|null $exclusiveMinimum = null,
		int|null $maxLength = null,
		int|null $minLength = null,
		int|null $maxItems = null,
		int|null $minItems = null,
		bool|null $uniqueItems = null,
		string|null $pattern = null,
		array|string|null $enum = null,
		Discriminator|null $discriminator = null,
		bool|null $readOnly = null,
		bool|null $writeOnly = null,
		Xml|null $xml = null,
		ExternalDocumentation|null $externalDocs = null,
		mixed $example = Generator::UNDEFINED,
		array|null $examples = null,
		bool|null $nullable = null,
		bool|null $deprecated = null,
		array|null $allOf = null,
		array|null $anyOf = null,
		array|null $oneOf = null,
		AdditionalProperties|bool|null $additionalProperties = null,
		mixed $const = Generator::UNDEFINED,
		// annotation
		array|null $x = null,
		array|null $attachables = null,
	)
	{
		parent::__construct([
			'ref' => $ref ?? Generator::UNDEFINED,
			'schema' => $schema ?? Generator::UNDEFINED,
			'title' => $title ?? Generator::UNDEFINED,
			'description' => $description ?? Generator::UNDEFINED,
			'maxProperties' => $maxProperties ?? Generator::UNDEFINED,
			'minProperties' => $minProperties ?? Generator::UNDEFINED,
			'required' => $required ?? Generator::UNDEFINED,
			'properties' => $properties ?? Generator::UNDEFINED,
			'type' => $type ?? Generator::UNDEFINED,
			'format' => $format ?? Generator::UNDEFINED,
			'collectionFormat' => $collectionFormat ?? Generator::UNDEFINED,
			'default' => $default,
			'maximum' => $maximum ?? Generator::UNDEFINED,
			'exclusiveMaximum' => $exclusiveMaximum ?? Generator::UNDEFINED,
			'minimum' => $minimum ?? Generator::UNDEFINED,
			'exclusiveMinimum' => $exclusiveMinimum ?? Generator::UNDEFINED,
			'maxLength' => $maxLength ?? Generator::UNDEFINED,
			'minLength' => $minLength ?? Generator::UNDEFINED,
			'maxItems' => $maxItems ?? Generator::UNDEFINED,
			'minItems' => $minItems ?? Generator::UNDEFINED,
			'uniqueItems' => $uniqueItems ?? Generator::UNDEFINED,
			'pattern' => $pattern ?? Generator::UNDEFINED,
			'enum' => $enum ?? Generator::UNDEFINED,
			'readOnly' => $readOnly ?? Generator::UNDEFINED,
			'writeOnly' => $writeOnly ?? Generator::UNDEFINED,
			'xml' => $xml ?? Generator::UNDEFINED,
			'example' => $example,
			'nullable' => $nullable ?? Generator::UNDEFINED,
			'deprecated' => $deprecated ?? Generator::UNDEFINED,
			'allOf' => $allOf ?? Generator::UNDEFINED,
			'anyOf' => $anyOf ?? Generator::UNDEFINED,
			'oneOf' => $oneOf ?? Generator::UNDEFINED,
			'additionalProperties' => $additionalProperties ?? Generator::UNDEFINED,
			'const' => $const,
			'x' => $x ?? Generator::UNDEFINED,
			'attachables' => $attachables ?? Generator::UNDEFINED,
			'value' => $this->combine($items, $discriminator, $externalDocs, $examples, $attachables),
		]);
	}

}
