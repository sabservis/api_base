<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;

final class FileUploadTest extends TestCase
{

	#[Test]
	public function defaultValues(): void
	{
		$attribute = new FileUpload(name: 'avatar');

		self::assertSame('avatar', $attribute->name);
		self::assertFalse($attribute->multiple);
		self::assertTrue($attribute->required);
		self::assertNull($attribute->description);
		self::assertNull($attribute->allowedTypes);
	}

	#[Test]
	public function withAllOptions(): void
	{
		$attribute = new FileUpload(
			name: 'documents',
			multiple: true,
			required: false,
			description: 'Upload your documents',
			allowedTypes: ['application/pdf', 'image/jpeg'],
		);

		self::assertSame('documents', $attribute->name);
		self::assertTrue($attribute->multiple);
		self::assertFalse($attribute->required);
		self::assertSame('Upload your documents', $attribute->description);
		self::assertSame(['application/pdf', 'image/jpeg'], $attribute->allowedTypes);
	}

	#[Test]
	public function canBeUsedAsAttribute(): void
	{
		$reflectionClass = new \ReflectionClass(TestControllerWithFileUpload::class);
		$method = $reflectionClass->getMethod('upload');

		$attributes = $method->getAttributes(FileUpload::class);

		self::assertCount(2, $attributes);

		/** @var FileUpload $avatar */
		$avatar = $attributes[0]->newInstance();
		self::assertSame('avatar', $avatar->name);
		self::assertFalse($avatar->multiple);

		/** @var FileUpload $documents */
		$documents = $attributes[1]->newInstance();
		self::assertSame('documents', $documents->name);
		self::assertTrue($documents->multiple);
	}

}

class TestControllerWithFileUpload
{

	#[FileUpload(name: 'avatar')]
	#[FileUpload(name: 'documents', multiple: true)]
	public function upload(): void
	{
	}

}
