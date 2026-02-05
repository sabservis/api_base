<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use UnitEnum;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Items implements OpenApiAttributeInterface
{

	use CompositionSupport;
	use EnumSupport;
	use ReferencedClassesSupport;

	/**
	 * @param class-string|string|null $ref
	 * @param class-string<UnitEnum>|array<mixed>|null $enum
	 * @param array<Schema|class-string>|null $oneOf
	 * @param array<Schema|class-string>|null $anyOf
	 * @param array<Schema|class-string>|null $allOf
	 */
	public function __construct(
		public string|null $ref = null,
		public string|null $type = null,
		public string|null $format = null,
		public string|array|null $enum = null,
		public int|float|null $minimum = null,
		public int|float|null $maximum = null,
		public int|null $minLength = null,
		public int|null $maxLength = null,
		public mixed $example = null,
		public array|null $oneOf = null,
		public array|null $anyOf = null,
		public array|null $allOf = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		if ($this->ref !== null) {
			return ['$ref' => '#/components/schemas/' . $this->resolveRefName($this->ref)];
		}

		$spec = [];

		if ($this->type !== null) {
			$spec['type'] = $this->type;
		}

		if ($this->format !== null) {
			$spec['format'] = $this->format;
		}

		if ($this->enum !== null) {
			$spec['enum'] = $this->resolveEnum();
		}

		if ($this->minimum !== null) {
			$spec['minimum'] = $this->minimum;
		}

		if ($this->maximum !== null) {
			$spec['maximum'] = $this->maximum;
		}

		if ($this->minLength !== null) {
			$spec['minLength'] = $this->minLength;
		}

		if ($this->maxLength !== null) {
			$spec['maxLength'] = $this->maxLength;
		}

		if ($this->example !== null) {
			$spec['example'] = $this->example;
		}

		if ($this->oneOf !== null) {
			$spec['oneOf'] = $this->buildCompositionArray($this->oneOf);
		}

		if ($this->anyOf !== null) {
			$spec['anyOf'] = $this->buildCompositionArray($this->anyOf);
		}

		if ($this->allOf !== null) {
			$spec['allOf'] = $this->buildCompositionArray($this->allOf);
		}

		return $spec;
	}

	/**
	 * @return array<mixed>
	 */
	public function resolveEnum(): array
	{
		return $this->doResolveEnum($this->enum);
	}

	/**
	 * Get all class references from ref and oneOf/anyOf/allOf for schema registration.
	 *
	 * @return array<class-string>
	 */
	public function getReferencedClasses(): array
	{
		return [
			...$this->extractRefClasses($this->ref),
			...$this->extractCompositionClasses($this->oneOf, $this->anyOf, $this->allOf),
		];
	}

}
