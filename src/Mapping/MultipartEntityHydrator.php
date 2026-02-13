<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Http\ApiRequest;
use function array_key_exists;
use function filter_var;
use function is_array;
use function is_subclass_of;
use const FILTER_VALIDATE_BOOLEAN;

class MultipartEntityHydrator
{

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
				// phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.NoAssignment
				/** @var class-string<BackedEnum> $typeName */
				$property->setValue($instance, $typeName::from($value));

				return;
			}
		}

		$property->setValue($instance, $this->castToType($value, $type));
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
