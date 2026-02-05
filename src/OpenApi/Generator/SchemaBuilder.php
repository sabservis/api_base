<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Generator;

use Closure;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionNamedType;
use ReflectionProperty;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\OpenApi\Spec\SchemaObject;
use Sabservis\Api\OpenApi\TypeMapper;
use UnitEnum;
use function class_exists;
use function end;
use function explode;

final class SchemaBuilder
{

	/** @var array<string, SchemaObject> */
	private array $schemas = [];

	/** @var array<string, bool> */
	private array $processing = [];

	/** @var array<string, bool> */
	private array $filtered = [];

	/**
	 * @param Closure(string): bool|null $schemaFilter Filter schemas by class name - return true to include
	 */
	public function __construct(private Closure|null $schemaFilter = null)
	{
	}

	/**
	 * Register a class and build its schema. Returns the reference name.
	 *
	 * @param class-string $className
	 */
	public function registerClass(string $className): string
	{
		$schemaName = $this->getSchemaName($className);

		// Already registered
		if (isset($this->schemas[$schemaName])) {
			return $schemaName;
		}

		// Already filtered out
		if (isset($this->filtered[$schemaName])) {
			return $schemaName;
		}

		// Apply schema filter
		if ($this->schemaFilter !== null && ($this->schemaFilter)($className) === false) {
			$this->filtered[$schemaName] = true;

			return $schemaName;
		}

		// Circular reference protection
		if (isset($this->processing[$schemaName])) {
			return $schemaName;
		}

		$this->processing[$schemaName] = true;

		try {
			$this->schemas[$schemaName] = $this->buildSchemaFromClass($className);
		} finally {
			unset($this->processing[$schemaName]);
		}

		return $schemaName;
	}

	/**
	 * Get a reference schema object for a class.
	 *
	 * @param class-string $className
	 */
	public function getReference(string $className): SchemaObject
	{
		$schemaName = $this->registerClass($className);

		return new SchemaObject(ref: $schemaName);
	}

	/**
	 * @return array<string, SchemaObject>
	 */
	public function getSchemas(): array
	{
		return $this->schemas;
	}

	public function hasSchema(string $schemaName): bool
	{
		return isset($this->schemas[$schemaName]);
	}

	public function addSchema(string $name, SchemaObject $schema): void
	{
		$this->schemas[$name] = $schema;
	}

	/**
	 * @param class-string $className
	 */
	private function buildSchemaFromClass(string $className): SchemaObject
	{
		$reflection = new ReflectionClass($className);
		$schemaAttr = $this->getSchemaAttribute($reflection);

		// Handle enums specially
		if ($reflection->isEnum()) {
			/** @var ReflectionClass<UnitEnum> $reflection */
			return $this->buildEnumSchema($reflection, $schemaAttr);
		}

		$properties = [];
		$required = [];

		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$propertyName = $this->getPropertyName($property);
			$propertySchema = $this->buildPropertySchema($property);

			$properties[$propertyName] = $propertySchema;

			// Check if required
			$type = $property->getType();

			if ($type instanceof ReflectionNamedType && !$type->allowsNull()) {
				$propertyAttr = $this->getPropertyAttribute($property);

				if ($propertyAttr === null || $propertyAttr->required !== false) {
					$required[] = $propertyName;
				}
			}
		}

		return new SchemaObject(
			type: 'object',
			title: $schemaAttr?->title,
			description: $schemaAttr?->description,
			properties: $properties !== [] ? $properties : null,
			required: $required !== [] ? $required : null,
			deprecated: $schemaAttr?->deprecated,
		);
	}

	/**
	 * Build schema for PHP enum.
	 *
	 * @param ReflectionClass<UnitEnum> $reflection
	 */
	private function buildEnumSchema(ReflectionClass $reflection, Schema|null $schemaAttr): SchemaObject
	{
		/** @var class-string<UnitEnum> $enumClass */
		$enumClass = $reflection->getName();
		$enumReflection = new ReflectionEnum($enumClass);
		$enumValues = [];

		foreach ($enumReflection->getCases() as $case) {
			$enumValues[] = $case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : $case->getName();
		}

		// Use type from Schema attribute, or infer from backing type
		$type = $schemaAttr?->type;

		if ($type === null && $enumReflection->isBacked()) {
			$backingType = $enumReflection->getBackingType();

			if ($backingType instanceof ReflectionNamedType) {
				$type = match ($backingType->getName()) {
					'int' => 'integer',
					default => 'string',
				};
			}
		}

		return new SchemaObject(
			type: $type ?? 'string',
			description: $schemaAttr?->description,
			enum: $enumValues !== [] ? $enumValues : null,
			deprecated: $schemaAttr?->deprecated,
		);
	}

	private function buildPropertySchema(ReflectionProperty $property): SchemaObject
	{
		$propertyAttr = $this->getPropertyAttribute($property);
		$type = $property->getType();

		// Start with type from reflection
		$schema = new SchemaObject();

		if ($type instanceof ReflectionNamedType) {
			$typeName = $type->getName();

			// Check for class type that needs reference
			if (!TypeMapper::isScalarType($typeName) && class_exists($typeName)) {
				// Register the class and return reference
				$schemaName = $this->registerClass($typeName);

				return new SchemaObject(ref: $schemaName);
			}

			// Map PHP type to OpenAPI type
			$openApiType = TypeMapper::phpToOpenApi($typeName);

			$schema = new SchemaObject(
				type: $openApiType['type'],
				format: $openApiType['format'] ?? null,
				nullable: $type->allowsNull() ? true : null,
			);
		}

		// Override with attribute values
		if ($propertyAttr !== null) {
			$schema = new SchemaObject(
				ref: $propertyAttr->ref !== null ? $this->handleRefAttribute($propertyAttr->ref) : null,
				type: $propertyAttr->type ?? $schema->type,
				format: $propertyAttr->format ?? $schema->format,
				title: $propertyAttr->title,
				description: $propertyAttr->description,
				enum: $propertyAttr->enum !== null ? $propertyAttr->resolveEnum() : null,
				minimum: $propertyAttr->minimum,
				maximum: $propertyAttr->maximum,
				minLength: $propertyAttr->minLength,
				maxLength: $propertyAttr->maxLength,
				pattern: $propertyAttr->pattern,
				default: $propertyAttr->default,
				example: $propertyAttr->example,
				nullable: $propertyAttr->nullable ?? $schema->nullable,
				deprecated: $propertyAttr->deprecated,
				readOnly: $propertyAttr->readOnly,
				writeOnly: $propertyAttr->writeOnly,
				items: $propertyAttr->items !== null ? $this->buildItemsSchema($propertyAttr->items) : null,
			);
		}

		return $schema;
	}

	private function buildItemsSchema(Items $items): SchemaObject
	{
		if ($items->ref !== null) {
			$schemaName = $this->handleRefAttribute($items->ref);

			return new SchemaObject(ref: $schemaName);
		}

		return new SchemaObject(
			type: $items->type,
			format: $items->format,
			enum: $items->enum !== null ? $items->resolveEnum() : null,
			minimum: $items->minimum,
			maximum: $items->maximum,
			minLength: $items->minLength,
			maxLength: $items->maxLength,
			example: $items->example,
		);
	}

	private function handleRefAttribute(string $ref): string
	{
		if (class_exists($ref)) {
			return $this->registerClass($ref);
		}

		return $ref;
	}

	private function getPropertyName(ReflectionProperty $property): string
	{
		$attr = $this->getPropertyAttribute($property);

		return $attr?->property ?? $property->getName();
	}

	/**
	 * @param ReflectionClass<object> $reflection
	 */
	private function getSchemaAttribute(ReflectionClass $reflection): Schema|null
	{
		$attributes = $reflection->getAttributes(Schema::class);

		if ($attributes === []) {
			return null;
		}

		return $attributes[0]->newInstance();
	}

	private function getPropertyAttribute(ReflectionProperty $property): Property|null
	{
		$attributes = $property->getAttributes(Property::class);

		if ($attributes === []) {
			return null;
		}

		return $attributes[0]->newInstance();
	}

	private function getSchemaName(string $className): string
	{
		$parts = explode('\\', $className);

		return end($parts);
	}

}
