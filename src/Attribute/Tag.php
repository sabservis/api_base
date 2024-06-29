<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute;

use Attribute;
use OpenApi\Annotations;
use OpenApi\Generator;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Tag extends Annotations\Tag
{

	protected mixed $internalValue = null;

	protected bool $isHidden;

	/**
	 * @param array<string,mixed>|null $x
	 * @param array<Attachable>|null $attachables
	 */
	public function __construct(
		string $name,
		mixed $value = null,
		string|null $description = null,
		ExternalDocumentation|null $externalDocs = null,
		// annotation
		array|null $x = null,
		array|null $attachables = null,
		bool $hidden = false,
	)
	{
		$this->internalValue = $value;
		$this->isHidden = $hidden;

		parent::__construct([
			'description' => $description ?? Generator::UNDEFINED,
			'name' => $name,
			'value' => $this->combine($externalDocs, $attachables),
			'x' => $x ?? Generator::UNDEFINED,
		]);
	}

	public function getValue(): mixed
	{
		return $this->internalValue;
	}

	public function isHidden(): bool
	{
		return $this->isHidden;
	}

}
