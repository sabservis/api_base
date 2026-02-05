<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;
use BackedEnum;
use UnitEnum;
use function array_map;
use function enum_exists;
use function is_array;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Items implements OpenApiAttributeInterface
{

	use CompositionSupport;

	/** @var array<mixed>|null */
	private array|null $resolvedEnum = null;

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
			return ['$ref' => '#/components/schemas/' . $this->getRefName()];
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

	private function getRefName(): string
	{
		if ($this->ref !== null && $this->looksLikeClassName($this->ref)) {
			return $this->getShortClassName($this->ref);
		}

		return $this->ref ?? '';
	}

	/**
	 * @return array<mixed>
	 */
	public function resolveEnum(): array
	{
		if ($this->resolvedEnum !== null) {
			return $this->resolvedEnum;
		}

		if ($this->enum === null) {
			return $this->resolvedEnum = [];
		}

		// Already an array of values
		if (is_array($this->enum)) {
			return $this->resolvedEnum = $this->enum;
		}

		// Enum class name - resolve to cases
		if (is_string($this->enum) && enum_exists($this->enum)) {
			/** @var class-string<UnitEnum> $enumClass */
			$enumClass = $this->enum;
			$cases = $enumClass::cases();

			return $this->resolvedEnum = array_map(
				static fn (UnitEnum $case) => $case instanceof BackedEnum ? $case->value : $case->name,
				$cases,
			);
		}

		return $this->resolvedEnum = [];
	}

	/**
	 * Get all class references from oneOf/anyOf/allOf for schema registration.
	 *
	 * Note: We don't use class_exists() here because this method is called during
	 * DI compilation (beforeCompile) when classes might not be autoloaded yet.
	 * Instead, we check if the string looks like a fully-qualified class name.
	 *
	 * @return array<class-string>
	 */
	public function getReferencedClasses(): array
	{
		$classes = [];

		if ($this->ref !== null && $this->looksLikeClassName($this->ref)) {
			/** @var class-string $className */
			$className = $this->ref;
			$classes[] = $className;
		}

		foreach ([$this->oneOf, $this->anyOf, $this->allOf] as $composition) {
			if ($composition === null) {
				continue;
			}

			foreach ($composition as $item) {
				if (is_string($item) && $this->looksLikeClassName($item)) {
					/** @var class-string $className */
					$className = $item;
					$classes[] = $className;
				}
			}
		}

		return $classes;
	}

}
