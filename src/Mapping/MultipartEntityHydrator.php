<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use BackedEnum;
use Pocta\DataMapper\Attributes\Ignore;
use Pocta\DataMapper\Attributes\OutputOnly;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use function array_key_exists;
use function array_map;
use function class_exists;
use function filter_var;
use function get_debug_type;
use function implode;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function sprintf;
use const FILTER_VALIDATE_BOOLEAN;

class MultipartEntityHydrator
{

	public function __construct(
		private readonly EntitySerializer|null $entitySerializer = null,
	)
	{
	}

	/**
	 * Hydrate a DTO instance from a multipart/form-data request.
	 *
	 * Regular properties are populated from parsed body ($_POST data).
	 * FileUpload properties are populated from uploaded files.
	 *
	 * @template T of object
	 * @param class-string<T> $className
	 * @return T
	 */
	public function hydrate(ApiRequest $request, string $className): object
	{
		$reflection = new ReflectionClass($className);
		$instance = $reflection->newInstanceWithoutConstructor();

		$parsedBody = $request->getParsedBody();
		$data = is_array($parsedBody) ? $parsedBody : [];

		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			// Same as data-mapper for JSON: never hydrate #[OutputOnly]/#[Ignore] from input
			if ($property->getAttributes(OutputOnly::class) !== [] || $property->getAttributes(Ignore::class) !== []) {
				continue;
			}

			$fileUploadAttrs = $property->getAttributes(FileUpload::class);

			if ($fileUploadAttrs !== []) {
				$this->hydrateFileProperty($request, $instance, $property, $fileUploadAttrs[0]->newInstance());
			} else {
				$this->hydrateScalarProperty($data, $instance, $property);
			}
		}

		return $instance;
	}

	private function hydrateFileProperty(
		ApiRequest $request,
		object $instance,
		ReflectionProperty $property,
		FileUpload $attribute,
	): void
	{
		$fieldName = $attribute->name !== '' ? $attribute->name : $property->getName();

		if ($attribute->multiple) {
			$property->setValue($instance, $request->getUploadedFiles($fieldName));
		} else {
			$file = $request->getUploadedFile($fieldName);

			if ($file !== null) {
				$property->setValue($instance, $file);
			} elseif ($this->isNullableProperty($property)) {
				$property->setValue($instance, null);
			}
			// Non-nullable with no file → leave uninitialized
		}
	}

	private function isNullableProperty(ReflectionProperty $property): bool
	{
		$type = $property->getType();

		if ($type === null) {
			return true;
		}

		return $type->allowsNull();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function hydrateScalarProperty(
		array $data,
		object $instance,
		ReflectionProperty $property,
	): void
	{
		$name = $property->getName();

		if (!isset($data[$name]) && !array_key_exists($name, $data)) {
			if ($property->hasDefaultValue()) {
				$property->setValue($instance, $property->getDefaultValue());
			}

			// No value and no default → leave uninitialized
			return;
		}

		$value = $data[$name];
		$type = $property->getType();

		if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
			$typeName = $type->getName();

			if (is_subclass_of($typeName, BackedEnum::class)) {
				/** @var class-string<BackedEnum> $enumClass */
				$enumClass = $typeName;
				// Empty form field (unselected <select>) means "no value" for a nullable enum
				$isEmpty = $value === null || ($value === '' && !$this->hasEmptyCase($enumClass));
				$property->setValue(
					$instance,
					$isEmpty && $type->allowsNull() ? null : $this->toEnum($enumClass, $name, $value),
				);

				return;
			}

			// Nested object from JSON string (multipart form-data)
			if (is_string($value) && class_exists($typeName) && $this->entitySerializer !== null) {
				$nested = $this->entitySerializer->deserialize($value, $typeName);
				$property->setValue($instance, $nested);

				return;
			}
		}

		$property->setValue($instance, $this->castToType($value, $type));
	}

	/**
	 * Form values are strings - int-backed enums accept integer strings ("10").
	 * Invalid value → 422 with the same message as data-mapper uses for JSON bodies.
	 *
	 * @param class-string<BackedEnum> $enumClass
	 */
	private function toEnum(string $enumClass, string $field, mixed $value): BackedEnum
	{
		$intBacked = (string) (new ReflectionEnum($enumClass))->getBackingType() === 'int';
		$backingValue = match (true) {
			$intBacked && is_int($value) => $value,
			$intBacked && is_string($value) && preg_match('/^-?\d+\z/', $value) === 1 => (int) $value,
			!$intBacked && (is_string($value) || is_int($value)) => (string) $value,
			default => null,
		};

		$enum = $backingValue !== null ? $enumClass::tryFrom($backingValue) : null;

		if ($enum === null) {
			$expected = implode(
				', ',
				array_map(static fn (BackedEnum $case): string => (string) $case->value, $enumClass::cases()),
			);

			throw (new ValidationException('Request body contains validation errors'))->withFields([
				$field => sprintf(
					"Invalid value for field '%s'. Expected one of: %s, got: %s",
					$field,
					$expected,
					is_scalar($value) ? (string) $value : get_debug_type($value),
				),
			]);
		}

		return $enum;
	}

	/**
	 * @param class-string<BackedEnum> $enumClass
	 */
	private function hasEmptyCase(string $enumClass): bool
	{
		foreach ($enumClass::cases() as $case) {
			if ($case->value === '') {
				return true;
			}
		}

		return false;
	}

	private function castToType(mixed $value, ReflectionType|null $type): mixed
	{
		if (!$type instanceof ReflectionNamedType) {
			return $value;
		}

		$typeName = $type->getName();

		return match ($typeName) {
			'int' => (int) $value,
			'float' => (float) $value,
			'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
			'string' => (string) $value,
			default => $value,
		};
	}

}
