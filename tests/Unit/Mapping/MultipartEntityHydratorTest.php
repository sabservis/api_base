<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\MultipartEntityHydrator;

enum TestCategory: string
{

	case News = 'news';

	case Blog = 'blog';

	case Tutorial = 'tutorial';

}

class StringAndFileDto
{

	public string $title;

	#[FileUpload]
	public UploadedFile $document;

}

class NullableFileDto
{

	#[FileUpload]
	public UploadedFile|null $avatar = null;

}

class MultipleFilesDto
{

	/** @var array<UploadedFile> */
	#[FileUpload(multiple: true)]
	public array $attachments;

}

class IntPropertyDto
{

	public int $count;

}

class BoolPropertyDto
{

	public bool $active;

}

class EnumPropertyDto
{

	public TestCategory $category;

}

class ExplicitFileNameDto
{

	#[FileUpload(name: 'profile_photo')]
	public UploadedFile|null $avatar = null;

}

class DefaultValueDto
{

	public string $status = 'draft';

}

class FloatPropertyDto
{

	public float $price;

}

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
			->withParsedBody(['title' => 'Hello World']);

		$dto = $this->hydrator->hydrate($request, StringAndFileDto::class);

		self::assertInstanceOf(StringAndFileDto::class, $dto);
		self::assertSame('Hello World', $dto->title);
	}

	#[Test]
	public function hydratesUploadedFileProperty(): void
	{
		$file = new UploadedFile('doc.pdf', 'application/pdf', 1_024, '/tmp/php123', 0);
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['title' => 'My Doc'])
			->withUploadedFiles(['document' => $file]);

		$dto = $this->hydrator->hydrate($request, StringAndFileDto::class);

		self::assertInstanceOf(StringAndFileDto::class, $dto);
		self::assertSame('My Doc', $dto->title);
		self::assertSame($file, $dto->document);
	}

	#[Test]
	public function hydratesNullableFileAsNull(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([]);

		$dto = $this->hydrator->hydrate($request, NullableFileDto::class);

		self::assertInstanceOf(NullableFileDto::class, $dto);
		self::assertNull($dto->avatar);
	}

	#[Test]
	public function hydratesMultipleFilesAsArray(): void
	{
		$file1 = new UploadedFile('a.jpg', 'image/jpeg', 100, '/tmp/a', 0);
		$file2 = new UploadedFile('b.jpg', 'image/jpeg', 200, '/tmp/b', 0);
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([])
			->withUploadedFiles(['attachments' => [$file1, $file2]]);

		$dto = $this->hydrator->hydrate($request, MultipleFilesDto::class);

		self::assertInstanceOf(MultipleFilesDto::class, $dto);
		self::assertCount(2, $dto->attachments);
		self::assertSame($file1, $dto->attachments[0]);
		self::assertSame($file2, $dto->attachments[1]);
	}

	#[Test]
	public function hydratesIntPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['count' => '42']);

		$dto = $this->hydrator->hydrate($request, IntPropertyDto::class);

		self::assertInstanceOf(IntPropertyDto::class, $dto);
		self::assertSame(42, $dto->count);
	}

	#[Test]
	public function hydratesBoolPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['active' => 'true']);

		$dto = $this->hydrator->hydrate($request, BoolPropertyDto::class);

		self::assertInstanceOf(BoolPropertyDto::class, $dto);
		self::assertTrue($dto->active);
	}

	#[Test]
	public function hydratesEnumPropertyFromPostData(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['category' => 'blog']);

		$dto = $this->hydrator->hydrate($request, EnumPropertyDto::class);

		self::assertInstanceOf(EnumPropertyDto::class, $dto);
		self::assertSame(TestCategory::Blog, $dto->category);
	}

	#[Test]
	public function explicitFileUploadNameUsedForLookup(): void
	{
		$file = new UploadedFile('photo.jpg', 'image/jpeg', 500, '/tmp/photo', 0);
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([])
			->withUploadedFiles(['profile_photo' => $file]);

		$dto = $this->hydrator->hydrate($request, ExplicitFileNameDto::class);

		self::assertInstanceOf(ExplicitFileNameDto::class, $dto);
		self::assertSame($file, $dto->avatar);
	}

	#[Test]
	public function optionalScalarPropertyUsesDefault(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([]);

		$dto = $this->hydrator->hydrate($request, DefaultValueDto::class);

		self::assertInstanceOf(DefaultValueDto::class, $dto);
		self::assertSame('draft', $dto->status);
	}

}
