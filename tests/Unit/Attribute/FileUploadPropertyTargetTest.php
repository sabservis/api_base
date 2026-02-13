<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Http\UploadedFile;
use function assert;

final class FileUploadPropertyTargetTest extends TestCase
{

	#[Test]
	public function canBeAppliedToProperty(): void
	{
		$reflectionClass = new ReflectionClass(DtoWithFileUpload::class);
		$property = $reflectionClass->getProperty('file');

		$attributes = $property->getAttributes(FileUpload::class);

		self::assertCount(1, $attributes);

		$fileUpload = $attributes[0]->newInstance();
		assert($fileUpload instanceof FileUpload);
		self::assertSame(['application/pdf'], $fileUpload->allowedTypes);
		self::assertTrue($fileUpload->required);
	}

	#[Test]
	public function propertyWithoutNameDefaultsToEmptyString(): void
	{
		$reflectionClass = new ReflectionClass(DtoWithFileUpload::class);
		$property = $reflectionClass->getProperty('file');

		$attributes = $property->getAttributes(FileUpload::class);
		$fileUpload = $attributes[0]->newInstance();
		assert($fileUpload instanceof FileUpload);

		self::assertSame('', $fileUpload->name);
	}

	#[Test]
	public function propertyWithExplicitNameKeepsIt(): void
	{
		$reflectionClass = new ReflectionClass(DtoWithFileUpload::class);
		$property = $reflectionClass->getProperty('namedFile');

		$attributes = $property->getAttributes(FileUpload::class);
		$fileUpload = $attributes[0]->newInstance();
		assert($fileUpload instanceof FileUpload);

		self::assertSame('my_document', $fileUpload->name);
	}

	#[Test]
	public function canBeAppliedToParameter(): void
	{
		$reflectionClass = new ReflectionClass(DtoWithFileUpload::class);
		$constructor = $reflectionClass->getConstructor();
		self::assertNotNull($constructor);

		$parameters = $constructor->getParameters();
		$fileParam = $parameters[0];

		$attributes = $fileParam->getAttributes(FileUpload::class);
		self::assertCount(1, $attributes);

		$fileUpload = $attributes[0]->newInstance();
		assert($fileUpload instanceof FileUpload);
		self::assertSame('', $fileUpload->name);
		self::assertSame(['image/png', 'image/jpeg'], $fileUpload->allowedTypes);
	}

	#[Test]
	public function stillWorksOnMethods(): void
	{
		$reflectionClass = new ReflectionClass(ControllerWithFileUploadBackwardCompat::class);
		$method = $reflectionClass->getMethod('upload');

		$attributes = $method->getAttributes(FileUpload::class);

		self::assertCount(1, $attributes);

		$fileUpload = $attributes[0]->newInstance();
		assert($fileUpload instanceof FileUpload);
		self::assertSame('avatar', $fileUpload->name);
		self::assertFalse($fileUpload->multiple);
	}

}

class DtoWithFileUpload
{

	#[FileUpload(allowedTypes: ['application/pdf'])]
	public UploadedFile $file;

	#[FileUpload(name: 'my_document')]
	public UploadedFile $namedFile;

	public function __construct(
		#[FileUpload(allowedTypes: ['image/png', 'image/jpeg'])]
		public UploadedFile $promotedFile,
	)
	{
	}

}

class ControllerWithFileUploadBackwardCompat
{

	#[FileUpload(name: 'avatar')]
	public function upload(): void
	{
		// Intentionally empty helper endpoint for attribute reflection tests.
	}

}
