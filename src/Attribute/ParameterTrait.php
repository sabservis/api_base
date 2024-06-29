<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute;

use OpenApi\Generator;

trait ParameterTrait
{

	/**
	 * @param string|class-string|object|null                         $ref
	 * @param array<Examples>                                         $examples
	 * @param array<MediaType>|JsonContent|XmlContent|Attachable|null $content
	 * @param array<string>|null                                      $spaceDelimited
	 * @param array<string>|null                                      $pipeDelimited
	 * @param array<string,mixed>|null                                $x
	 * @param array<Attachable>|null                                       $attachables
	 */
	public function __construct(
		string|null $parameter = null,
		string|null $name = null,
		string|null $description = null,
		string|null $in = null,
		bool|null $required = null,
		bool|null $deprecated = null,
		bool|null $allowEmptyValue = null,
		string|object|null $ref = null,
		Schema|null $schema = null,
		mixed $example = Generator::UNDEFINED,
		array|null $examples = null,
		array|JsonContent|XmlContent|Attachable|null $content = null,
		string|null $style = null,
		bool|null $explode = null,
		bool|null $allowReserved = null,
		array|null $spaceDelimited = null,
		array|null $pipeDelimited = null,
		// annotation
		array|null $x = null,
		array|null $attachables = null,
	)
	{
		parent::__construct([
			'parameter' => $parameter ?? Generator::UNDEFINED,
			'name' => $name ?? Generator::UNDEFINED,
			'description' => $description ?? Generator::UNDEFINED,
			'in' => Generator::isDefault($this->in) ? $in : $this->in,
			'required' => $required ?? Generator::UNDEFINED,
			'deprecated' => $deprecated ?? Generator::UNDEFINED,
			'allowEmptyValue' => $allowEmptyValue ?? Generator::UNDEFINED,
			'ref' => $ref ?? Generator::UNDEFINED,
			'example' => $example,
			'style' => $style ?? Generator::UNDEFINED,
			'explode' => $explode ?? Generator::UNDEFINED,
			'allowReserved' => $allowReserved ?? Generator::UNDEFINED,
			'spaceDelimited' => $spaceDelimited ?? Generator::UNDEFINED,
			'pipeDelimited' => $pipeDelimited ?? Generator::UNDEFINED,
			'x' => $x ?? Generator::UNDEFINED,
			'value' => $this->combine($schema, $examples, $content, $attachables),
		]);
	}

}
