<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Generator;

use Sabservis\Api\OpenApi\Spec\SchemaObject;
use function array_key_first;
use function count;
use function in_array;
use function is_array;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Named examples (one per discriminator value) for a polymorphic schema
 * (oneOf/anyOf + discriminator with mapping).
 *
 * Swagger UI cannot build a sample for such a root schema - it drops the discriminator
 * property, so every "Try it out" request fails. Named examples on the media type
 * are offered in a dropdown and pasted as a complete payload.
 *
 * Values are composed only from what the schemas declare (example, single-value enum,
 * nested objects) - nothing is invented, properties without a value are omitted.
 * Works over finished schemas (after the discriminator values were applied to variants).
 */
final class ExampleBuilder
{

	private const MAX_DEPTH = 3;

	private const REF_PREFIX = '#/components/schemas/';

	/**
	 * @param array<string, SchemaObject> $schemas Component schemas (SchemaBuilder::getSchemas())
	 */
	public function __construct(private array $schemas)
	{
	}

	/**
	 * @return array<string, array{summary: string, description?: string, value: array<string, mixed>}>|null
	 *     null when the schema is not polymorphic
	 */
	public function buildDiscriminatorExamples(string $schemaName): array|null
	{
		$schema = $this->schemas[$schemaName] ?? null;
		$discriminator = $schema?->discriminator;

		if (
			$schema === null
			|| $discriminator?->mapping === null
			|| $discriminator->mapping === []
			|| ($schema->oneOf === null && $schema->anyOf === null)
		) {
			return null;
		}

		$examples = [];

		foreach ($discriminator->mapping as $value => $target) {
			$variantName = $this->refName($target);
			$variant = $this->schemas[$variantName] ?? null;

			if ($variant === null) {
				continue;
			}

			$example = ['summary' => $variant->title ?? $variantName];

			if ($variant->description !== null) {
				$example['description'] = $variant->description;
			}

			$example['value'] = $this->variantValue(
				$variant,
				$discriminator->propertyName,
				$value,
				0,
				[$schemaName, $variantName],
			);

			$examples[(string) $value] = $example;
		}

		return $examples !== [] ? $examples : null;
	}

	/**
	 * Payload of one variant: discriminator first, then the other properties in schema order.
	 *
	 * @param list<string> $stack
	 * @return array<string, mixed>
	 */
	private function variantValue(
		SchemaObject $variant,
		string $propertyName,
		int|string $discriminatorValue,
		int $depth,
		array $stack,
	): array
	{
		$property = $variant->properties[$propertyName] ?? null;
		$value = [$propertyName => $this->discriminatorValue($property, $discriminatorValue)];

		return $value + $this->objectValue($variant, $depth, $stack, $propertyName);
	}

	/**
	 * Mapping keys are strings in the spec; PHP turns numeric ones into int - follow the property type.
	 */
	private function discriminatorValue(SchemaObject|null $property, int|string $value): int|string
	{
		if ($property?->type === 'integer') {
			return (int) $value;
		}

		return (string) $value;
	}

	/**
	 * @param list<string> $stack
	 * @return array<string, mixed>
	 */
	private function objectValue(SchemaObject $schema, int $depth, array $stack, string|null $skip = null): array
	{
		$value = [];

		foreach ($schema->properties ?? [] as $name => $property) {
			if ($name === $skip) {
				continue;
			}

			$propertyValue = $this->value($property, $depth, $stack);

			if ($propertyValue !== null) {
				$value[$name] = $propertyValue[0];
			}
		}

		return $value;
	}

	/**
	 * @param list<string> $stack Schema names on the current path (cycle detection)
	 * @return array{mixed}|null null = no value (omit the property)
	 */
	private function value(SchemaObject $schema, int $depth, array $stack): array|null
	{
		if ($schema->readOnly === true || $schema->deprecated === true) {
			return null;
		}

		if ($schema->example !== null) {
			return [$schema->example];
		}

		if ($schema->enum !== null && count($schema->enum) === 1) {
			return [$schema->enum[0]];
		}

		if ($schema->ref !== null) {
			return $this->refValue($schema->ref, $depth, $stack);
		}

		if ($schema->oneOf !== null && $schema->oneOf !== []) {
			return $this->compositionValue($schema, $depth, $stack);
		}

		if ($schema->type === 'array') {
			if ($schema->items?->ref === null) {
				return null;
			}

			$item = $this->refValue($schema->items->ref, $depth, $stack);

			// Only object items (rule: array of $ref objects), empty result → omit
			return $item !== null && is_array($item[0]) && $item[0] !== [] ? [[$item[0]]] : null;
		}

		if ($schema->properties !== null && $schema->properties !== []) {
			if ($depth >= self::MAX_DEPTH) {
				return null;
			}

			$value = $this->objectValue($schema, $depth + 1, $stack);

			return $value !== [] ? [$value] : null;
		}

		return null;
	}

	/**
	 * @param list<string> $stack
	 * @return array{mixed}|null
	 */
	private function refValue(string $ref, int $depth, array $stack): array|null
	{
		$name = $this->refName($ref);
		$target = $this->schemas[$name] ?? null;

		if ($target === null || in_array($name, $stack, true)) {
			return null;
		}

		return $this->value($target, $depth, [...$stack, $name]);
	}

	/**
	 * First variant; a nested polymorphic schema with mapping gets its first discriminator value.
	 *
	 * @param list<string> $stack
	 * @return array{mixed}|null
	 */
	private function compositionValue(SchemaObject $schema, int $depth, array $stack): array|null
	{
		$mapping = $schema->discriminator?->mapping;

		if ($schema->discriminator !== null && $mapping !== null && $mapping !== []) {
			$discriminatorValue = array_key_first($mapping);
			$variantName = $this->refName($mapping[$discriminatorValue]);
			$variant = $this->schemas[$variantName] ?? null;

			if ($variant === null || in_array($variantName, $stack, true) || $depth >= self::MAX_DEPTH) {
				return null;
			}

			return [$this->variantValue(
				$variant,
				$schema->discriminator->propertyName,
				$discriminatorValue,
				$depth + 1,
				[...$stack, $variantName],
			)];
		}

		$first = $schema->oneOf[array_key_first($schema->oneOf ?? [])] ?? null;

		return $first !== null ? $this->value($first, $depth, $stack) : null;
	}

	private function refName(string $ref): string
	{
		return str_starts_with($ref, self::REF_PREFIX) ? substr($ref, strlen(self::REF_PREFIX)) : $ref;
	}

}
