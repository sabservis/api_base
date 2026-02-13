# Multipart DTO File Upload Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable defining mixed multipart/form-data requests (regular fields + file uploads) in a single DTO class, with framework hydration of `UploadedFile` into DTO properties.

**Architecture:** Extend `#[FileUpload]` attribute to work on DTO properties. When `OpenApiGenerator` encounters a DTO with FileUpload properties, generate `multipart/form-data` instead of `application/json`. At runtime, `ApiDispatcher` detects multipart DTOs and hydrates them from `$_POST` + uploaded files instead of JSON body. Validation rules use PHP type for required (nullable = optional) and FileUpload attribute for allowedTypes/multiple.

**Tech Stack:** PHP 8.1+, PHPUnit, PHPStan level 8, Slevomat CS

**Key constraint:** NEVER modify existing tests. If they fail, the implementation is wrong.

---

### Task 1: Extend FileUpload attribute target

**Files:**
- Modify: `src/Attribute/OpenApi/FileUpload.php:21`

**Step 1: Write the failing test**

Create file `tests/Unit/Attribute/FileUploadPropertyTargetTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\FileUploadPropertyTarget;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabservis\Api\Attribute\OpenApi\FileUpload;

final class FileUploadPropertyTargetTest extends TestCase
{

	#[Test]
	public function attributeCanBeAppliedToProperty(): void
	{
		$reflection = new ReflectionProperty(FileUploadPropertyDto::class, 'file');
		$attributes = $reflection->getAttributes(FileUpload::class);

		self::assertCount(1, $attributes);

		$instance = $attributes[0]->newInstance();
		self::assertSame('application/pdf', $instance->allowedTypes[0]);
	}

	#[Test]
	public function nameDefaultsToPropertyNameWhenOmitted(): void
	{
		$reflection = new ReflectionProperty(FileUploadPropertyDto::class, 'file');
		$attributes = $reflection->getAttributes(FileUpload::class);
		$instance = $attributes[0]->newInstance();

		// When name is not provided, it should be empty string (resolved later by builder)
		self::assertSame('', $instance->name);
	}

	#[Test]
	public function nameCanBeExplicitlySetOnProperty(): void
	{
		$reflection = new ReflectionProperty(FileUploadPropertyDto::class, 'avatar');
		$attributes = $reflection->getAttributes(FileUpload::class);
		$instance = $attributes[0]->newInstance();

		self::assertSame('profile_photo', $instance->name);
	}

	#[Test]
	public function attributeStillWorksOnMethod(): void
	{
		$reflection = new \ReflectionMethod(FileUploadPropertyDto::class, 'someMethod');
		$attributes = $reflection->getAttributes(FileUpload::class);

		self::assertCount(1, $attributes);
		self::assertSame('doc', $attributes[0]->newInstance()->name);
	}

}

class FileUploadPropertyDto
{

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public mixed $file;

	#[FileUpload(name: 'profile_photo', allowedTypes: ['image/png'])]
	public mixed $avatar;

	#[FileUpload(name: 'doc')]
	public function someMethod(): void
	{
	}

}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Attribute/FileUploadPropertyTargetTest.php -v`
Expected: FAIL - Attribute FileUpload cannot target property

**Step 3: Implement the change**

In `src/Attribute/OpenApi/FileUpload.php`:

1. Change line 21 from:
```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
```
to:
```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
```

2. Make `$name` parameter optional (default `''`):
```php
public function __construct(
    public string $name = '',
    public bool $multiple = false,
    public bool $required = true,
    public string|null $description = null,
    public array|null $allowedTypes = null,
)
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Attribute/FileUploadPropertyTargetTest.php -v`
Expected: PASS

**Step 5: Run all existing tests to verify backward compatibility**

Run: `./vendor/bin/phpunit -v`
Expected: All existing tests PASS

**Step 6: Commit**

```bash
git add src/Attribute/OpenApi/FileUpload.php tests/Unit/Attribute/FileUploadPropertyTargetTest.php
git commit -m "feat: extend FileUpload attribute to support property and parameter targets"
```

---

### Task 2: Add multipart DTO detection to RequestBodyBuilder

**Files:**
- Modify: `src/OpenApi/Loader/RequestBodyBuilder.php`
- Test: `tests/Unit/OpenApi/Loader/RequestBodyBuilderTest.php` (add new test class for DTO detection)

This task adds the ability to scan a DTO class for `#[FileUpload]` properties and build a multipart/form-data spec from them.

**Step 1: Write the failing test**

Create file `tests/Unit/OpenApi/Loader/MultipartDtoDetectionTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Loader\RequestBodyBuilder;

final class MultipartDtoDetectionTest extends TestCase
{

	private RequestBodyBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new RequestBodyBuilder();
	}

	#[Test]
	public function detectsFileUploadPropertiesInDto(): void
	{
		self::assertTrue($this->builder->hasFileUploadProperties(MultipartDto::class));
	}

	#[Test]
	public function returnsFalseForRegularDto(): void
	{
		self::assertFalse($this->builder->hasFileUploadProperties(RegularDto::class));
	}

	#[Test]
	public function buildsMultipartSpecFromDto(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartDto::class);

		self::assertNotNull($result);
		self::assertSame('multipart/form-data', array_key_first($result['contentSpec']));

		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		// Regular property
		self::assertArrayHasKey('type', $schema['properties']);

		// File upload property - name derived from property name
		self::assertArrayHasKey('file', $schema['properties']);
		self::assertSame('string', $schema['properties']['file']['type']);
		self::assertSame('binary', $schema['properties']['file']['format']);

		// Required: non-nullable properties
		self::assertContains('type', $schema['required']);
		self::assertContains('file', $schema['required']);
	}

	#[Test]
	public function optionalFileUploadPropertyIsNotRequired(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartWithOptionalDto::class);
		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		self::assertNotContains('thumbnail', $schema['required'] ?? []);
	}

	#[Test]
	public function multipleFileUploadGeneratesArraySchema(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartWithMultipleDto::class);
		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		self::assertSame('array', $schema['properties']['attachments']['type']);
		self::assertSame('string', $schema['properties']['attachments']['items']['type']);
		self::assertSame('binary', $schema['properties']['attachments']['items']['format']);
	}

	#[Test]
	public function fileUploadInstancesAreExtractedFromProperties(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartDto::class);

		self::assertArrayHasKey('fileUploads', $result);
		self::assertCount(1, $result['fileUploads']);
		self::assertInstanceOf(FileUpload::class, $result['fileUploads'][0]);
		self::assertSame('file', $result['fileUploads'][0]->name);
	}

	#[Test]
	public function explicitFileUploadNameOverridesPropertyName(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartWithExplicitNameDto::class);
		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		self::assertArrayHasKey('profile_photo', $schema['properties']);
		self::assertArrayNotHasKey('avatar', $schema['properties']);
	}

	#[Test]
	public function descriptionFromFileUploadAttribute(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartWithDescriptionDto::class);
		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		self::assertSame('PDF document', $schema['properties']['document']['description']);
	}

	#[Test]
	public function regularPropertyWithPropertyAttributePreservesDescription(): void
	{
		$result = $this->builder->buildMultipartRequestBody(MultipartDto::class);
		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		// Regular property should have type info (not binary)
		self::assertArrayHasKey('type', $schema['properties']);
		self::assertArrayNotHasKey('format', $schema['properties']['type']);
	}

}

// --- Test fixtures ---

class MultipartDto
{

	#[Property(description: 'Document type')]
	public string $type;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

}

class RegularDto
{

	public string $name;

	public string $email;

}

class MultipartWithOptionalDto
{

	public string $type;

	#[FileUpload]
	public UploadedFile $file;

	#[FileUpload]
	public ?UploadedFile $thumbnail = null;

}

class MultipartWithMultipleDto
{

	public string $type;

	#[FileUpload(multiple: true)]
	public array $attachments;

}

class MultipartWithExplicitNameDto
{

	#[FileUpload(name: 'profile_photo')]
	public UploadedFile $avatar;

}

class MultipartWithDescriptionDto
{

	#[FileUpload(description: 'PDF document')]
	public UploadedFile $document;

}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/OpenApi/Loader/MultipartDtoDetectionTest.php -v`
Expected: FAIL - method hasFileUploadProperties does not exist

**Step 3: Implement `hasFileUploadProperties` and `buildMultipartRequestBody`**

Add to `src/OpenApi/Loader/RequestBodyBuilder.php`:

```php
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Sabservis\Api\Attribute\OpenApi\Property;

/**
 * Check if a DTO class has any properties with #[FileUpload] attribute.
 *
 * @param class-string $className
 */
public function hasFileUploadProperties(string $className): bool
{
    $reflection = new ReflectionClass($className);

    foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        if ($property->getAttributes(FileUpload::class) !== []) {
            return true;
        }
    }

    return false;
}

/**
 * Build multipart/form-data request body spec from DTO with FileUpload properties.
 *
 * Regular properties become form fields, FileUpload properties become binary fields.
 * Required is derived from PHP type (non-nullable = required).
 *
 * @param class-string $className
 * @return array<mixed>
 */
public function buildMultipartRequestBody(string $className): array
{
    $reflection = new ReflectionClass($className);
    $properties = [];
    $required = [];
    $fileUploads = [];

    foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $fileUploadAttrs = $property->getAttributes(FileUpload::class);

        if ($fileUploadAttrs !== []) {
            // File upload property
            $fileUpload = $fileUploadAttrs[0]->newInstance();
            assert($fileUpload instanceof FileUpload);

            // Resolve field name: explicit name from attribute, or property name
            $fieldName = $fileUpload->name !== '' ? $fileUpload->name : $property->getName();

            // Update the FileUpload instance name for runtime use
            $resolvedFileUpload = new FileUpload(
                name: $fieldName,
                multiple: $fileUpload->multiple,
                required: !$this->isNullableProperty($property),
                description: $fileUpload->description,
                allowedTypes: $fileUpload->allowedTypes,
            );

            $fileUploads[] = $resolvedFileUpload;

            $fileProperty = [
                'type' => 'string',
                'format' => 'binary',
            ];

            if ($fileUpload->description !== null) {
                $fileProperty['description'] = $fileUpload->description;
            }

            if ($fileUpload->multiple) {
                $properties[$fieldName] = [
                    'type' => 'array',
                    'items' => $fileProperty,
                ];

                if ($fileUpload->description !== null) {
                    $properties[$fieldName]['description'] = $fileUpload->description;
                }
            } else {
                $properties[$fieldName] = $fileProperty;
            }

            // Required from PHP type
            if (!$this->isNullableProperty($property)) {
                $required[] = $fieldName;
            }
        } else {
            // Regular form field property
            $propertyName = $property->getName();

            // Check for Property attribute
            $propertyAttr = $property->getAttributes(Property::class);
            $propertySpec = [];

            if ($propertyAttr !== []) {
                $propInstance = $propertyAttr[0]->newInstance();
                assert($propInstance instanceof Property);

                if ($propInstance->description !== null) {
                    $propertySpec['description'] = $propInstance->description;
                }
            }

            // Derive type from PHP reflection
            $type = $property->getType();

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                if (class_exists($typeName)) {
                    // Class reference (e.g., enum) - will be handled by OpenApiGenerator as $ref
                    $propertySpec['_classRef'] = $typeName;
                } else {
                    $propertySpec['type'] = $this->phpTypeToOpenApi($typeName);
                }
            }

            $properties[$propertyName] = $propertySpec;

            // Required from PHP type
            if ($type instanceof ReflectionNamedType && !$type->allowsNull()) {
                $required[] = $propertyName;
            }
        }
    }

    $schema = [
        'type' => 'object',
        'properties' => $properties,
    ];

    if ($required !== []) {
        $schema['required'] = $required;
    }

    return [
        'description' => 'Multipart form data',
        'entity' => $className,
        'required' => $required !== [],
        'contentSpec' => [
            'multipart/form-data' => [
                'schema' => $schema,
            ],
        ],
        'fileUploads' => $fileUploads,
    ];
}

private function isNullableProperty(ReflectionProperty $property): bool
{
    $type = $property->getType();

    if ($type === null) {
        return true;
    }

    return $type->allowsNull();
}

private function phpTypeToOpenApi(string $type): string
{
    return match ($type) {
        'int' => 'integer',
        'float' => 'number',
        'bool' => 'boolean',
        default => 'string',
    };
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/OpenApi/Loader/MultipartDtoDetectionTest.php -v`
Expected: PASS

**Step 5: Run all existing tests**

Run: `./vendor/bin/phpunit -v`
Expected: All PASS

**Step 6: Commit**

```bash
git add src/OpenApi/Loader/RequestBodyBuilder.php tests/Unit/OpenApi/Loader/MultipartDtoDetectionTest.php
git commit -m "feat: add multipart DTO detection and spec building to RequestBodyBuilder"
```

---

### Task 3: Wire multipart DTO detection into OpenApiGenerator

**Files:**
- Modify: `src/OpenApi/Generator/OpenApiGenerator.php:337-361`
- Test: new `tests/Unit/OpenApi/Generator/MultipartDtoOpenApiTest.php`

When `buildRequestBody()` encounters an entity class with FileUpload properties, generate `multipart/form-data` with inline schema instead of `application/json` with `$ref`.

**Step 1: Write the failing test**

Create file `tests/Unit/OpenApi/Generator/MultipartDtoOpenApiTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class MultipartDtoOpenApiTest extends TestCase
{

	#[Test]
	public function dtoWithFileUploadGeneratesMultipartFormData(): void
	{
		$spec = $this->generateSpec(MultipartOpenApiController::class);

		$requestBody = $spec['paths']['/upload']['post']['requestBody'];

		// Must be multipart/form-data, NOT application/json
		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);
		self::assertArrayNotHasKey('application/json', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];
		self::assertSame('object', $schema['type']);

		// Regular field
		self::assertArrayHasKey('type', $schema['properties']);

		// File field
		self::assertArrayHasKey('file', $schema['properties']);
		self::assertSame('string', $schema['properties']['file']['type']);
		self::assertSame('binary', $schema['properties']['file']['format']);

		// Required
		self::assertContains('type', $schema['required']);
		self::assertContains('file', $schema['required']);
	}

	#[Test]
	public function regularDtoStillGeneratesApplicationJson(): void
	{
		$spec = $this->generateSpec(RegularBodyOpenApiController::class);

		$requestBody = $spec['paths']['/regular']['post']['requestBody'];

		self::assertArrayHasKey('application/json', $requestBody['content']);
		self::assertArrayNotHasKey('multipart/form-data', $requestBody['content']);
	}

	#[Test]
	public function multipartDtoWithEnumGeneratesRefForEnumProperty(): void
	{
		$spec = $this->generateSpec(MultipartWithEnumController::class);

		$requestBody = $spec['paths']['/with-enum']['post']['requestBody'];
		$schema = $requestBody['content']['multipart/form-data']['schema'];

		// Enum property should be a $ref
		$typeProperty = $schema['properties']['formalityType'];
		self::assertArrayHasKey('$ref', $typeProperty);
		self::assertStringContainsString('MultipartTestFormalityType', $typeProperty['$ref']);

		// Enum should be registered in components/schemas
		self::assertArrayHasKey('MultipartTestFormalityType', $spec['components']['schemas']);
	}

	#[Test]
	public function multipartDtoOptionalFileNotInRequired(): void
	{
		$spec = $this->generateSpec(MultipartOptionalFileController::class);

		$requestBody = $spec['paths']['/optional-file']['post']['requestBody'];
		$schema = $requestBody['content']['multipart/form-data']['schema'];

		self::assertNotContains('thumbnail', $schema['required'] ?? []);
		self::assertContains('file', $schema['required']);
	}

	#[Test]
	public function multipartDtoMultipleFilesGeneratesArraySchema(): void
	{
		$spec = $this->generateSpec(MultipartMultipleFilesController::class);

		$requestBody = $spec['paths']['/multiple-files']['post']['requestBody'];
		$schema = $requestBody['content']['multipart/form-data']['schema'];

		self::assertSame('array', $schema['properties']['attachments']['type']);
		self::assertSame('string', $schema['properties']['attachments']['items']['type']);
		self::assertSame('binary', $schema['properties']['attachments']['items']['format']);
	}

	#[Test]
	public function methodLevelFileUploadStillWorks(): void
	{
		$spec = $this->generateSpec(MethodLevelFileUploadController::class);

		$requestBody = $spec['paths']['/method-upload']['post']['requestBody'];

		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);
		self::assertSame('string', $requestBody['content']['multipart/form-data']['schema']['properties']['avatar']['type']);
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<mixed>
	 */
	private function generateSpec(string $controllerClass): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('test.controller')
			->setType($controllerClass);

		$schemaArray = (new OpenApiAttributeLoader($containerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);

		$config = new OpenApiConfig();
		$config->title = 'Test';
		$config->version = '1.0';

		$generator = new OpenApiGenerator($config);
		$json = $generator->generateJson($schema);

		return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
	}

}

// --- Test fixtures ---

#[Schema]
class MultipartOpenApiDto
{

	#[Property(description: 'Document type')]
	public string $type;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

}

class MultipartOpenApiController implements Controller
{

	#[Post(path: '/upload')]
	#[RequestBody(ref: MultipartOpenApiDto::class)]
	#[Response(code: 200)]
	public function upload(MultipartOpenApiDto $dto): array
	{
		return [];
	}

}

#[Schema]
class RegularBodyDto
{

	public string $name;

	public string $email;

}

class RegularBodyOpenApiController implements Controller
{

	#[Post(path: '/regular')]
	#[RequestBody(ref: RegularBodyDto::class)]
	#[Response(code: 200)]
	public function create(RegularBodyDto $dto): array
	{
		return [];
	}

}

#[Schema(type: 'string')]
enum MultipartTestFormalityType: string
{

	case Invoice = 'invoice';
	case Contract = 'contract';

}

#[Schema]
class MultipartWithEnumDto
{

	public MultipartTestFormalityType $formalityType;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

}

class MultipartWithEnumController implements Controller
{

	#[Post(path: '/with-enum')]
	#[RequestBody(ref: MultipartWithEnumDto::class)]
	#[Response(code: 200)]
	public function upload(MultipartWithEnumDto $dto): array
	{
		return [];
	}

}

#[Schema]
class MultipartOptionalFileDto
{

	#[FileUpload]
	public UploadedFile $file;

	#[FileUpload]
	public ?UploadedFile $thumbnail = null;

}

class MultipartOptionalFileController implements Controller
{

	#[Post(path: '/optional-file')]
	#[RequestBody(ref: MultipartOptionalFileDto::class)]
	#[Response(code: 200)]
	public function upload(MultipartOptionalFileDto $dto): array
	{
		return [];
	}

}

#[Schema]
class MultipartMultipleFilesDto
{

	public string $label;

	#[FileUpload(multiple: true)]
	public array $attachments;

}

class MultipartMultipleFilesController implements Controller
{

	#[Post(path: '/multiple-files')]
	#[RequestBody(ref: MultipartMultipleFilesDto::class)]
	#[Response(code: 200)]
	public function upload(MultipartMultipleFilesDto $dto): array
	{
		return [];
	}

}

class MethodLevelFileUploadController implements Controller
{

	#[Post(path: '/method-upload')]
	#[FileUpload(name: 'avatar')]
	#[Response(code: 200)]
	public function upload(): array
	{
		return [];
	}

}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/OpenApi/Generator/MultipartDtoOpenApiTest.php -v`
Expected: FAIL - generates application/json instead of multipart/form-data

**Step 3: Modify `OpenApiGenerator::buildRequestBody()`**

In `src/OpenApi/Generator/OpenApiGenerator.php`, modify the `buildRequestBody` method (line ~337). Before the existing entity handling block, add multipart DTO detection:

```php
private function buildRequestBody(Endpoint $endpoint): RequestBodyObject|null
{
    $requestBody = $endpoint->getRequestBody();

    if ($requestBody === null) {
        return null;
    }

    $entity = $requestBody->getEntity();

    // If we have an entity class reference, check if it's a multipart DTO
    if ($entity !== null && class_exists($entity)) {
        // Check if DTO has FileUpload properties -> multipart/form-data
        if ($requestBody->hasFileUploads() && $requestBody->getContentSpec() !== null) {
            // Multipart DTO: build inline schema with file and form fields
            return $this->buildMultipartRequestBody($requestBody);
        }

        // Regular DTO: application/json with $ref
        $schemaName = $this->schemaBuilder->registerClass($entity);

        return new RequestBodyObject(
            content: [
                'application/json' => new MediaTypeObject(
                    schema: new SchemaObject(ref: $schemaName),
                ),
            ],
            description: $requestBody->getDescription() ?: null,
            required: $requestBody->isRequired() ?: null,
        );
    }

    // ... rest of existing contentSpec handling ...
}

/**
 * Build multipart/form-data request body from DTO with FileUpload properties.
 */
private function buildMultipartRequestBody(EndpointRequestBody $requestBody): RequestBodyObject
{
    $contentSpec = $requestBody->getContentSpec();
    $content = [];

    foreach ($contentSpec as $mediaType => $spec) {
        $schema = null;

        if (isset($spec['schema'])) {
            // Process properties: resolve class references to $ref
            $schemaSpec = $spec['schema'];

            if (isset($schemaSpec['properties'])) {
                foreach ($schemaSpec['properties'] as $name => $propSpec) {
                    if (isset($propSpec['_classRef'])) {
                        $className = $propSpec['_classRef'];
                        $schemaName = $this->schemaBuilder->registerClass($className);
                        $schemaSpec['properties'][$name] = ['$ref' => '#/components/schemas/' . $schemaName];

                        // Preserve description if set
                        if (isset($propSpec['description'])) {
                            $schemaSpec['properties'][$name]['description'] = $propSpec['description'];
                        }
                    }
                }
            }

            $schema = $this->buildSchemaFromSpec($schemaSpec);
        }

        $encoding = $spec['encoding'] ?? null;

        $content[$mediaType] = new MediaTypeObject(
            schema: $schema,
            encoding: $encoding,
        );
    }

    return new RequestBodyObject(
        content: $content,
        description: $requestBody->getDescription() ?: null,
        required: $requestBody->isRequired() ?: null,
    );
}
```

Also need to add import: `use Sabservis\Api\Schema\EndpointRequestBody;`

**Step 4: Wire the detection in OpenApiAttributeLoader and ArrayHydrator**

In `src/OpenApi/Loader/OpenApiAttributeLoader.php`, after line 348 (`parseFileUploadAttributes`), add DTO-based multipart detection. When `requestBody` has an `entity` that contains FileUpload properties, replace with multipart spec:

In the `parseMethod` flow (around line 354-358), after `inferRequestBodyFromMethodSignature`, add:

```php
// Check if inferred/declared request body entity has FileUpload properties
if (
    $parsed->requestBody !== null
    && isset($parsed->requestBody['entity'])
    && is_string($parsed->requestBody['entity'])
    && class_exists($parsed->requestBody['entity'])
    && $this->requestBodyBuilder->hasFileUploadProperties($parsed->requestBody['entity'])
) {
    // Validate: no method-level FileUpload + DTO FileUpload at the same time
    $methodFileUploads = $method->getAttributes(FileUpload::class);
    if ($methodFileUploads !== []) {
        throw new InvalidStateException(
            sprintf(
                'Method %s::%s() has both method-level #[FileUpload] and a DTO with FileUpload properties. Use one or the other.',
                $controllerClass,
                $method->getName(),
            ),
        );
    }

    $parsed->requestBody = $this->requestBodyBuilder->buildMultipartRequestBody(
        $parsed->requestBody['entity'],
    );
}
```

Add import: `use Sabservis\Api\Attribute\OpenApi\FileUpload;`

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/OpenApi/Generator/MultipartDtoOpenApiTest.php -v`
Expected: PASS

**Step 6: Run all tests**

Run: `./vendor/bin/phpunit -v`
Expected: All PASS

**Step 7: Commit**

```bash
git add src/OpenApi/Generator/OpenApiGenerator.php src/OpenApi/Loader/OpenApiAttributeLoader.php tests/Unit/OpenApi/Generator/MultipartDtoOpenApiTest.php
git commit -m "feat: generate multipart/form-data for DTOs with FileUpload properties"
```

---

### Task 4: Conflict detection - method FileUpload + DTO FileUpload

**Files:**
- Test: new `tests/Unit/OpenApi/Loader/MultipartDtoConflictTest.php`

This validates that using `#[FileUpload]` on method AND having a DTO with FileUpload properties throws a configuration exception.

**Step 1: Write the failing test**

Create `tests/Unit/OpenApi/Loader/MultipartDtoConflictTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\UI\Controller\Controller;

final class MultipartDtoConflictTest extends TestCase
{

	#[Test]
	public function throwsWhenMethodAndDtoBothHaveFileUpload(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('test.controller')
			->setType(ConflictingFileUploadController::class);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('both method-level #[FileUpload] and a DTO');

		(new OpenApiAttributeLoader($containerBuilder))->load();
	}

}

class ConflictingDto
{

	#[FileUpload]
	public UploadedFile $file;

}

class ConflictingFileUploadController implements Controller
{

	#[Post(path: '/conflict')]
	#[RequestBody(ref: ConflictingDto::class)]
	#[FileUpload(name: 'extra')]
	public function upload(): array
	{
		return [];
	}

}
```

**Step 2: Run test**

Run: `./vendor/bin/phpunit tests/Unit/OpenApi/Loader/MultipartDtoConflictTest.php -v`
Expected: PASS (conflict detection was implemented in Task 3)

If it fails, fix the detection logic from Task 3.

**Step 3: Commit**

```bash
git add tests/Unit/OpenApi/Loader/MultipartDtoConflictTest.php
git commit -m "test: add conflict detection test for method + DTO FileUpload"
```

---

### Task 5: Runtime multipart DTO hydration in ApiDispatcher

**Files:**
- Modify: `src/Dispatcher/ApiDispatcher.php:128-166`
- Create: `src/Mapping/MultipartEntityHydrator.php`
- Test: new `tests/Unit/Mapping/MultipartEntityHydratorTest.php`

This is the runtime side: when a request comes in with a multipart DTO, hydrate the DTO from `$_POST` + uploaded files.

**Step 1: Write the failing test for MultipartEntityHydrator**

Create `tests/Unit/Mapping/MultipartEntityHydratorTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\MultipartEntityHydrator;
use function file_put_contents;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const UPLOAD_ERR_OK;

final class MultipartEntityHydratorTest extends TestCase
{

	private MultipartEntityHydrator $hydrator;

	protected function setUp(): void
	{
		$this->hydrator = new MultipartEntityHydrator();
	}

	#[Test]
	public function hydratesStringPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['type' => 'invoice']);

		$dto = $this->hydrator->hydrate($request, HydrationTestDto::class);

		self::assertInstanceOf(HydrationTestDto::class, $dto);
		self::assertSame('invoice', $dto->type);
	}

	#[Test]
	public function hydratesUploadedFileProperty(): void
	{
		$content = 'file contents';
		$tmpFile = $this->createTempFile($content);

		$uploadedFile = new UploadedFile('test.pdf', 'application/pdf', strlen($content), $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['type' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);

		try {
			$dto = $this->hydrator->hydrate($request, HydrationTestDto::class);

			self::assertInstanceOf(UploadedFile::class, $dto->file);
			self::assertSame('test.pdf', $dto->file->getName());
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function hydratesNullableFileAsNull(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['type' => 'invoice']);

		$dto = $this->hydrator->hydrate($request, HydrationOptionalFileDto::class);

		self::assertNull($dto->thumbnail);
	}

	#[Test]
	public function hydratesMultipleFilesAsArray(): void
	{
		$content = 'file contents';
		$tmpFile1 = $this->createTempFile($content);
		$tmpFile2 = $this->createTempFile($content);

		$files = [
			new UploadedFile('a.txt', 'text/plain', strlen($content), $tmpFile1, UPLOAD_ERR_OK),
			new UploadedFile('b.txt', 'text/plain', strlen($content), $tmpFile2, UPLOAD_ERR_OK),
		];

		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([])
			->withUploadedFiles(['attachments' => $files]);

		try {
			$dto = $this->hydrator->hydrate($request, HydrationMultipleFilesDto::class);

			self::assertIsArray($dto->attachments);
			self::assertCount(2, $dto->attachments);
			self::assertInstanceOf(UploadedFile::class, $dto->attachments[0]);
		} finally {
			@unlink($tmpFile1);
			@unlink($tmpFile2);
		}
	}

	#[Test]
	public function hydratesIntPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['count' => '5']);

		$dto = $this->hydrator->hydrate($request, HydrationIntDto::class);

		self::assertSame(5, $dto->count);
	}

	#[Test]
	public function hydratesBoolPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['active' => 'true']);

		$dto = $this->hydrator->hydrate($request, HydrationBoolDto::class);

		self::assertTrue($dto->active);
	}

	#[Test]
	public function hydratesEnumPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['status' => 'active']);

		$dto = $this->hydrator->hydrate($request, HydrationEnumDto::class);

		self::assertSame(HydrationTestStatus::Active, $dto->status);
	}

	#[Test]
	public function explicitFileUploadNameUsedForLookup(): void
	{
		$content = 'file contents';
		$tmpFile = $this->createTempFile($content);

		$uploadedFile = new UploadedFile('photo.png', 'image/png', strlen($content), $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([])
			->withUploadedFiles(['profile_photo' => $uploadedFile]);

		try {
			$dto = $this->hydrator->hydrate($request, HydrationExplicitNameDto::class);

			self::assertInstanceOf(UploadedFile::class, $dto->avatar);
			self::assertSame('photo.png', $dto->avatar->getName());
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function optionalScalarPropertyUsesDefault(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([]);

		$dto = $this->hydrator->hydrate($request, HydrationOptionalScalarDto::class);

		self::assertSame('default_value', $dto->label);
	}

	private function createTempFile(string $content): string
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'hydrate_test_');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, $content);

		return $tmpFile;
	}

}

// --- Test fixtures ---

class HydrationTestDto
{

	public string $type;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

}

class HydrationOptionalFileDto
{

	public string $type;

	#[FileUpload]
	public ?UploadedFile $thumbnail = null;

}

class HydrationMultipleFilesDto
{

	#[FileUpload(multiple: true)]
	public array $attachments;

}

class HydrationIntDto
{

	public int $count;

}

class HydrationBoolDto
{

	public bool $active;

}

enum HydrationTestStatus: string
{

	case Active = 'active';
	case Inactive = 'inactive';

}

class HydrationEnumDto
{

	public HydrationTestStatus $status;

}

class HydrationExplicitNameDto
{

	#[FileUpload(name: 'profile_photo')]
	public UploadedFile $avatar;

}

class HydrationOptionalScalarDto
{

	public string $label = 'default_value';

}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Mapping/MultipartEntityHydratorTest.php -v`
Expected: FAIL - class MultipartEntityHydrator does not exist

**Step 3: Implement MultipartEntityHydrator**

Create `src/Mapping/MultipartEntityHydrator.php`:

```php
<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\UploadedFile;
use function assert;
use function filter_var;
use function is_array;
use function is_subclass_of;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Hydrates a DTO from multipart/form-data request.
 *
 * Regular properties are populated from parsed body ($_POST).
 * FileUpload properties are populated from uploaded files.
 */
final class MultipartEntityHydrator
{

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return T
	 */
	public function hydrate(ApiRequest $request, string $className): object
	{
		$reflection = new ReflectionClass($className);
		$instance = $reflection->newInstanceWithoutConstructor();

		$parsedBody = $request->getParsedBody();
		$postData = is_array($parsedBody) ? $parsedBody : [];

		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$fileUploadAttrs = $property->getAttributes(FileUpload::class);

			if ($fileUploadAttrs !== []) {
				$this->hydrateFileProperty($request, $instance, $property, $fileUploadAttrs[0]->newInstance());
			} else {
				$this->hydrateFormProperty($postData, $instance, $property);
			}
		}

		return $instance;
	}

	private function hydrateFileProperty(
		ApiRequest $request,
		object $instance,
		ReflectionProperty $property,
		FileUpload $fileUpload,
	): void
	{
		$fieldName = $fileUpload->name !== '' ? $fileUpload->name : $property->getName();

		if ($fileUpload->multiple) {
			$files = $request->getUploadedFiles($fieldName);
			$property->setValue($instance, $files);
		} else {
			$file = $request->getUploadedFile($fieldName);
			$property->setValue($instance, $file);
		}
	}

	/**
	 * @param array<string, mixed> $postData
	 */
	private function hydrateFormProperty(
		array $postData,
		object $instance,
		ReflectionProperty $property,
	): void
	{
		$name = $property->getName();
		$type = $property->getType();

		if (!isset($postData[$name])) {
			// Use default value if property has one
			if ($property->hasDefaultValue()) {
				$property->setValue($instance, $property->getDefaultValue());
			}

			return;
		}

		$value = $postData[$name];

		if ($type instanceof ReflectionNamedType) {
			$value = $this->castValue($value, $type);
		}

		$property->setValue($instance, $value);
	}

	private function castValue(mixed $value, ReflectionNamedType $type): mixed
	{
		$typeName = $type->getName();

		// Backed enums
		if (is_subclass_of($typeName, BackedEnum::class)) {
			return $typeName::from($value);
		}

		return match ($typeName) {
			'int' => (int) $value,
			'float' => (float) $value,
			'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
			'string' => (string) $value,
			default => $value,
		};
	}

}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Mapping/MultipartEntityHydratorTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Mapping/MultipartEntityHydrator.php tests/Unit/Mapping/MultipartEntityHydratorTest.php
git commit -m "feat: add MultipartEntityHydrator for DTO hydration from multipart requests"
```

---

### Task 6: Wire MultipartEntityHydrator into ApiDispatcher

**Files:**
- Modify: `src/Dispatcher/ApiDispatcher.php`
- Modify: `src/DI/ApiExtension.php` (to inject MultipartEntityHydrator)
- Test: new `tests/Integration/FileUpload/MultipartDtoPipelineIntegrationTest.php`

**Step 1: Write the failing integration test**

Create `tests/Integration/FileUpload/MultipartDtoPipelineIntegrationTest.php`:

```php
<?php declare(strict_types = 1);

namespace Tests\Integration\FileUpload;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\MultipartEntityHydrator;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_OK;

final class MultipartDtoPipelineIntegrationTest extends TestCase
{

	#[Test]
	public function fullPipelineHydratesDtoWithFileAndFormField(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'pdf-content-here';
		$tmpFile = $this->createTempFile($content);

		$uploadedFile = new UploadedFile('doc.pdf', 'application/pdf', strlen($content), $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/formality'))
			->withParsedBody(['type' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);

		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());
			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);

			self::assertSame('invoice', $payload['type']);
			self::assertSame('doc.pdf', $payload['fileName']);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function missingRequiredFileReturns400(): void
	{
		$dispatcher = $this->createDispatcher();

		$request = (new ApiRequest(method: 'POST', uri: '/formality'))
			->withParsedBody(['type' => 'invoice']);

		$response = new ApiResponse();

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);

		$dispatcher->dispatch($request, $response);
	}

	#[Test]
	public function invalidMimeTypeReturns415(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'not a real pdf';
		$tmpFile = $this->createTempFile($content);

		$uploadedFile = new UploadedFile('doc.pdf', 'application/pdf', strlen($content), $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/formality'))
			->withParsedBody(['type' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);

		$response = new ApiResponse();

		try {
			$this->expectException(ClientErrorException::class);
			$this->expectExceptionCode(415);

			$dispatcher->dispatch($request, $response);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function optionalFileCanBeMissing(): void
	{
		$dispatcher = $this->createDispatcher();
		$content = 'pdf-content-here';
		$tmpFile = $this->createTempFile($content);

		// Create a real PDF-like file (minimal PDF header for finfo detection)
		file_put_contents($tmpFile, "%PDF-1.4\n");
		$size = strlen("%PDF-1.4\n");

		$uploadedFile = new UploadedFile('doc.pdf', 'application/pdf', $size, $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/with-optional'))
			->withParsedBody(['label' => 'test'])
			->withUploadedFiles(['file' => $uploadedFile]);

		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());
			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('test', $payload['label']);
			self::assertFalse($payload['hasThumbnail']);
		} finally {
			@unlink($tmpFile);
		}
	}

	#[Test]
	public function multipleFilesAreHydratedIntoArray(): void
	{
		$dispatcher = $this->createDispatcher();
		$content1 = 'text content 1';
		$content2 = 'text content 2';
		$tmpFile1 = $this->createTempFile($content1);
		$tmpFile2 = $this->createTempFile($content2);

		$files = [
			new UploadedFile('a.txt', 'text/plain', strlen($content1), $tmpFile1, UPLOAD_ERR_OK),
			new UploadedFile('b.txt', 'text/plain', strlen($content2), $tmpFile2, UPLOAD_ERR_OK),
		];

		$request = (new ApiRequest(method: 'POST', uri: '/with-multiple'))
			->withParsedBody([])
			->withUploadedFiles(['docs' => $files]);

		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());
			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame(2, $payload['count']);
		} finally {
			@unlink($tmpFile1);
			@unlink($tmpFile2);
		}
	}

	#[Test]
	public function enumPropertyIsHydratedFromFormData(): void
	{
		$dispatcher = $this->createDispatcher();
		$tmpFile = $this->createTempFile("%PDF-1.4\n");
		$size = strlen("%PDF-1.4\n");

		$uploadedFile = new UploadedFile('doc.pdf', 'application/pdf', $size, $tmpFile, UPLOAD_ERR_OK);

		$request = (new ApiRequest(method: 'POST', uri: '/with-enum'))
			->withParsedBody(['formalityType' => 'invoice'])
			->withUploadedFiles(['file' => $uploadedFile]);

		$response = new ApiResponse();

		try {
			$result = $dispatcher->dispatch($request, $response);

			self::assertSame(200, $result->getStatusCode());
			$payload = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('invoice', $payload['formalityType']);
		} finally {
			@unlink($tmpFile);
		}
	}

	private function createDispatcher(): ApiDispatcher
	{
		$schemaLoaderContainerBuilder = new ContainerBuilder();
		$schemaLoaderContainerBuilder->addDefinition('multipart.controller')
			->setType(MultipartDtoPipelineController::class);

		$schemaArray = (new OpenApiAttributeLoader($schemaLoaderContainerBuilder))->load();
		$schema = (new ArrayHydrator())->hydrate($schemaArray);
		$router = new Router($schema);

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(MultipartDtoPipelineController::class)
			->willReturn(new MultipartDtoPipelineController());

		$serializer = $this->createMock(EntitySerializer::class);
		$serializer->method('serialize')
			->willReturnCallback(static fn (mixed $data): string => json_encode($data, JSON_THROW_ON_ERROR));

		$handler = new ServiceHandler($container, $serializer);
		$parameterMapping = new RequestParameterMapping();
		$multipartHydrator = new MultipartEntityHydrator();

		return new ApiDispatcher($router, $handler, $serializer, $parameterMapping, multipartHydrator: $multipartHydrator);
	}

	private function createTempFile(string $contents): string
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'multipart_test_');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, $contents);

		return $tmpFile;
	}

}

// --- Test fixtures ---

#[Schema(type: 'string')]
enum PipelineTestFormalityType: string
{

	case Invoice = 'invoice';
	case Contract = 'contract';

}

#[Schema]
class PipelineFormalityDto
{

	public string $type;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

}

#[Schema]
class PipelineOptionalFileDto
{

	public string $label;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

	#[FileUpload]
	public ?UploadedFile $thumbnail = null;

}

#[Schema]
class PipelineMultipleFilesDto
{

	#[FileUpload(multiple: true, allowedTypes: ['text/plain'])]
	public array $docs;

}

#[Schema]
class PipelineEnumDto
{

	public PipelineTestFormalityType $formalityType;

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

}

class MultipartDtoPipelineController implements Controller
{

	#[Post(path: '/formality')]
	#[RequestBody(ref: PipelineFormalityDto::class)]
	public function addFormality(PipelineFormalityDto $dto): array
	{
		return [
			'type' => $dto->type,
			'fileName' => $dto->file->getName(),
		];
	}

	#[Post(path: '/with-optional')]
	#[RequestBody(ref: PipelineOptionalFileDto::class)]
	public function withOptional(PipelineOptionalFileDto $dto): array
	{
		return [
			'label' => $dto->label,
			'hasThumbnail' => $dto->thumbnail !== null,
		];
	}

	#[Post(path: '/with-multiple')]
	#[RequestBody(ref: PipelineMultipleFilesDto::class)]
	public function withMultiple(PipelineMultipleFilesDto $dto): array
	{
		return [
			'count' => count($dto->docs),
		];
	}

	#[Post(path: '/with-enum')]
	#[RequestBody(ref: PipelineEnumDto::class)]
	public function withEnum(PipelineEnumDto $dto): array
	{
		return [
			'formalityType' => $dto->formalityType->value,
			'fileName' => $dto->file->getName(),
		];
	}

}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Integration/FileUpload/MultipartDtoPipelineIntegrationTest.php -v`
Expected: FAIL - ApiDispatcher doesn't accept multipartHydrator parameter

**Step 3: Modify ApiDispatcher to support multipart DTO hydration**

In `src/Dispatcher/ApiDispatcher.php`:

1. Add constructor parameter:
```php
public function __construct(
    private readonly Router $router,
    private readonly ServiceHandler $handler,
    private readonly EntitySerializer $serializer,
    private readonly RequestParameterMapping $parameterMapping,
    private readonly EntityValidator|null $validator = null,
    private readonly AuthorizationChecker|null $authorizationChecker = null,
    private readonly MultipartEntityHydrator|null $multipartHydrator = null,
)
```

2. Modify `transformRequest()` to detect multipart DTOs:

```php
private function transformRequest(ApiRequest $request): ApiRequest
{
    $endpoint = $request->getAttribute(RequestAttributes::Endpoint->value);
    assert($endpoint instanceof Endpoint);

    $requestBody = $endpoint->getRequestBody();

    if ($requestBody?->getEntity() === null) {
        return $request;
    }

    // Multipart DTO: hydrate from form data + uploaded files
    if ($requestBody->hasFileUploads() && $this->multipartHydrator !== null) {
        $dto = $this->multipartHydrator->hydrate($request, $requestBody->getEntity());

        if ($this->validator !== null) {
            $this->validator->validate($dto);
        }

        return $request
            ->withParsedBody($dto)
            ->withAttribute(RequestAttributes::RequestEntity->value, $dto);
    }

    // Existing JSON body handling...
    $body = $request->getContents();
    // ... (rest unchanged)
}
```

3. Add imports:
```php
use Sabservis\Api\Mapping\MultipartEntityHydrator;
```

**Step 4: Run integration test**

Run: `./vendor/bin/phpunit tests/Integration/FileUpload/MultipartDtoPipelineIntegrationTest.php -v`
Expected: PASS

**Step 5: Run ALL existing tests**

Run: `./vendor/bin/phpunit -v`
Expected: All PASS (including the original FileUploadPipelineIntegrationTest)

**Step 6: Wire into DI container**

Check `src/DI/ApiExtension.php` and add `MultipartEntityHydrator` service registration.

Look for where `ApiDispatcher` is registered and add `MultipartEntityHydrator` as a dependency.

**Step 7: Commit**

```bash
git add src/Dispatcher/ApiDispatcher.php src/DI/ApiExtension.php tests/Integration/FileUpload/MultipartDtoPipelineIntegrationTest.php
git commit -m "feat: wire MultipartEntityHydrator into ApiDispatcher for runtime DTO hydration"
```

---

### Task 7: Static analysis and code style

**Files:**
- All modified files

**Step 1: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/ --memory-limit=512M`
Expected: No errors (fix any that appear)

**Step 2: Run PHPCS**

Run: `./vendor/bin/phpcs --standard=ruleset.xml src/ tests/`
Expected: No errors (fix any that appear)

**Step 3: Run full test suite one final time**

Run: `./vendor/bin/phpunit -v`
Expected: All PASS

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "style: fix phpstan and phpcs issues in multipart DTO feature"
```

---

### Task 8: Update documentation

**Files:**
- Modify: `docs/openapi.md` - add Multipart DTO section
- Modify: `docs/cheatsheet.md` - add multipart DTO example to Soubory section
- Modify: `docs/README.md` - mention multipart DTO in Tier 3
- Modify: `CLAUDE.md` - update architecture notes if needed

**Step 1: Add to `docs/openapi.md`**

Add new section after existing "File Upload" section:

```markdown
## Multipart DTO (File Upload + Form Fields)

Pro endpointy, kde potřebuješ kombinaci běžných polí a souborů v jednom požadavku:

```php
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\UploadedFile;

#[Schema]
class AddFormalityRequest
{
    #[Property(description: 'Typ náležitosti')]
    public FormalityType $type;

    #[FileUpload(allowedTypes: ['application/pdf'])]
    public UploadedFile $file;

    #[FileUpload]
    public ?UploadedFile $thumbnail = null;  // volitelný

    #[FileUpload(multiple: true)]
    public array $attachments;               // více souborů
}
```

Controller:
```php
#[Post(path: '/formality')]
#[RequestBody(ref: AddFormalityRequest::class)]
public function add(AddFormalityRequest $dto): array
{
    $dto->type;          // FormalityType enum
    $dto->file;          // UploadedFile
    $dto->thumbnail;     // UploadedFile|null
    $dto->attachments;   // array<UploadedFile>
}
```

**Klíčová pravidla:**
- Required/optional se odvozuje z PHP typu (`UploadedFile` = required, `?UploadedFile` = optional)
- `allowedTypes` a další validace z `#[FileUpload]` atributu
- `name` parametr je volitelný - bez něj se použije název property
- Nelze kombinovat `#[FileUpload]` na metodě a DTO s FileUpload properties na stejném endpointu
- Framework automaticky generuje `multipart/form-data` místo `application/json`
```

**Step 2: Add to `docs/cheatsheet.md`** Soubory section

Add after existing file upload examples:
```markdown
# Multipart DTO (form fields + soubory)
#[RequestBody(ref: MyMultipartDto::class)]  // DTO s #[FileUpload] properties → multipart/form-data
```

**Step 3: Update `docs/README.md`** Tier 3 table

Add row:
```markdown
| `#[FileUpload]` na DTO property | Multipart form data s běžnými poli + soubory |
```

**Step 4: Commit**

```bash
git add docs/
git commit -m "docs: add multipart DTO file upload documentation"
```

---

### Task 9: Final verification

**Step 1: Run complete test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All PASS, no existing tests broken

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/ --memory-limit=512M`
Expected: 0 errors

**Step 3: Run PHPCS**

Run: `./vendor/bin/phpcs --standard=ruleset.xml src/ tests/`
Expected: 0 errors

**Step 4: Review git log**

Run: `git log --oneline -10`
Expected: Clean commit history for this feature
