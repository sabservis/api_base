<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Attributes\Ignore;
use Pocta\DataMapper\Attributes\InputOnly;
use Pocta\DataMapper\Attributes\OutputOnly;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Mapping\MultipartEntityHydrator;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;

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

enum MultipartPriority: int
{

	case Low = 1;

	case High = 10;

}

class IntEnumPropertyDto
{

	public MultipartPriority $priority;

}

class NullableEnumPropertyDto
{

	public TestCategory|null $category = null;

}

enum MultipartVisibility: string
{

	case Default = '';

	case Hidden = 'hidden';

}

class NullableEnumWithEmptyCaseDto
{

	public MultipartVisibility|null $visibility = null;

}

class NullableScalarsDto
{

	public int|null $quantity = null;

	public float|null $price = null;

	public bool|null $active = null;

	public string|null $note = null;

	public NestedAddressDto|null $address = null;

	public int $count = 5;

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

class NestedAddressDto
{

	public string $street;

	public string $city;

}

class ParentWithNestedObjectDto
{

	public string $name;

	public NestedAddressDto $address;

}

class ParentWithNullableNestedObjectDto
{

	public string $name;

	public NestedAddressDto|null $address = null;

}

class DirectionMultipartDto
{

	public string $name;

	#[OutputOnly]
	public string $role = 'user';

	#[Ignore]
	public string $note = 'internal';

	#[InputOnly]
	public string $password = '';

	#[OutputOnly]
	#[FileUpload]
	public UploadedFile|null $avatar = null;

}

final class MultipartEntityHydratorTest extends TestCase
{

	private MultipartEntityHydrator $hydrator;

	protected function setUp(): void
	{
		$this->hydrator = new MultipartEntityHydrator(new DataMapperSerializer());
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

	#[Test]
	public function hydratesNestedObjectFromJsonString(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody([
				'name' => 'John',
				'address' => '{"street":"Main St","city":"Prague"}',
			]);

		$dto = $this->hydrator->hydrate($request, ParentWithNestedObjectDto::class);

		self::assertInstanceOf(ParentWithNestedObjectDto::class, $dto);
		self::assertSame('John', $dto->name);
		self::assertInstanceOf(NestedAddressDto::class, $dto->address);
		self::assertSame('Main St', $dto->address->street);
		self::assertSame('Prague', $dto->address->city);
	}

	#[Test]
	public function hydratesNullableNestedObjectAsNullWhenMissing(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['name' => 'John']);

		$dto = $this->hydrator->hydrate($request, ParentWithNullableNestedObjectDto::class);

		self::assertInstanceOf(ParentWithNullableNestedObjectDto::class, $dto);
		self::assertSame('John', $dto->name);
		self::assertNull($dto->address);
	}

	#[Test]
	public function outputOnlyPropertyIsNotHydratedFromInput(): void
	{
		$dto = $this->hydrateDirectionDto();

		self::assertSame('user', $dto->role);
		self::assertNull($dto->avatar);
	}

	#[Test]
	public function ignoredPropertyIsNotHydratedFromInput(): void
	{
		self::assertSame('internal', $this->hydrateDirectionDto()->note);
	}

	#[Test]
	public function inputOnlyAndPlainPropertiesAreHydrated(): void
	{
		$dto = $this->hydrateDirectionDto();

		self::assertSame('John', $dto->name);
		self::assertSame('secret', $dto->password);
	}

	private function hydrateDirectionDto(): DirectionMultipartDto
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['name' => 'John', 'role' => 'admin', 'note' => 'x', 'password' => 'secret'])
			->withUploadedFiles(['avatar' => new UploadedFile('a.png', 'image/png', 3, '/tmp/a', 0)]);

		$dto = $this->hydrator->hydrate($request, DirectionMultipartDto::class);
		self::assertInstanceOf(DirectionMultipartDto::class, $dto);

		return $dto;
	}

	#[Test]
	public function invalidEnumValueThrowsValidationException(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['category' => 'podcast']);

		try {
			$this->hydrator->hydrate($request, EnumPropertyDto::class);
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			self::assertSame(422, $e->getCode());
			self::assertSame('Request body contains validation errors', $e->getMessage());
			self::assertSame(
				['validation' => ['category' => "Invalid value for field 'category'. Expected one of: news, blog, tutorial, got: podcast"]],
				$e->getContext(),
			);
		}
	}

	#[Test]
	public function nonScalarEnumValueThrowsValidationException(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['category' => ['blog']]);

		$this->expectException(ValidationException::class);

		$this->hydrator->hydrate($request, EnumPropertyDto::class);
	}

	#[Test]
	public function emptyStringForNullableEnumHydratesNull(): void
	{
		// Unselected <select> sends an empty string
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['category' => '']);

		$dto = $this->hydrator->hydrate($request, NullableEnumPropertyDto::class);

		self::assertInstanceOf(NullableEnumPropertyDto::class, $dto);
		self::assertNull($dto->category);
	}

	#[Test]
	public function emptyStringForNonNullableEnumThrowsValidationException(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['category' => '']);

		$this->expectException(ValidationException::class);

		$this->hydrator->hydrate($request, EnumPropertyDto::class);
	}

	#[Test]
	public function emptyStringMatchingEnumCaseHydratesThatCase(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['visibility' => '']);

		$dto = $this->hydrator->hydrate($request, NullableEnumWithEmptyCaseDto::class);

		self::assertInstanceOf(NullableEnumWithEmptyCaseDto::class, $dto);
		self::assertSame(MultipartVisibility::Default, $dto->visibility);
	}

	#[Test]
	public function invalidValueForNullableEnumThrowsValidationException(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['category' => 'podcast']);

		$this->expectException(ValidationException::class);

		$this->hydrator->hydrate($request, NullableEnumPropertyDto::class);
	}

	#[Test]
	public function emptyStringForNullablePropertiesHydratesNull(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['quantity' => '', 'price' => '', 'active' => '', 'address' => '']);

		$dto = $this->hydrator->hydrate($request, NullableScalarsDto::class);

		self::assertInstanceOf(NullableScalarsDto::class, $dto);
		self::assertNull($dto->quantity);
		self::assertNull($dto->price);
		self::assertNull($dto->active);
		self::assertNull($dto->address);
	}

	#[Test]
	public function emptyStringStaysStringAndNonNullableKeepsCast(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['note' => '', 'count' => '']);

		$dto = $this->hydrator->hydrate($request, NullableScalarsDto::class);

		self::assertInstanceOf(NullableScalarsDto::class, $dto);
		self::assertSame('', $dto->note);
		self::assertSame(0, $dto->count);
	}

	#[Test]
	public function nonEmptyNullableValuesAreCast(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['quantity' => '3', 'price' => '1.5', 'active' => 'true']);

		$dto = $this->hydrator->hydrate($request, NullableScalarsDto::class);

		self::assertInstanceOf(NullableScalarsDto::class, $dto);
		self::assertSame(3, $dto->quantity);
		self::assertSame(1.5, $dto->price);
		self::assertTrue($dto->active);
	}

	#[Test]
	public function hydratesIntBackedEnumFromNumericString(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['priority' => '10']);

		$dto = $this->hydrator->hydrate($request, IntEnumPropertyDto::class);

		self::assertInstanceOf(IntEnumPropertyDto::class, $dto);
		self::assertSame(MultipartPriority::High, $dto->priority);
	}

	#[Test]
	public function invalidIntBackedEnumValueThrowsValidationException(): void
	{
		$request = (new ApiRequest(method: 'POST', uri: '/test'))
			->withParsedBody(['priority' => 'abc']);

		$this->expectException(ValidationException::class);

		$this->hydrator->hydrate($request, IntEnumPropertyDto::class);
	}

}
