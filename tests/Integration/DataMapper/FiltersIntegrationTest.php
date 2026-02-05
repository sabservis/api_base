<?php declare(strict_types = 1);

namespace Tests\Integration\DataMapper;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Attributes\Filters\DefaultValueFilter;
use Pocta\DataMapper\Attributes\Filters\EachFilter;
use Pocta\DataMapper\Attributes\Filters\NormalizeEmailFilter;
use Pocta\DataMapper\Attributes\Filters\NormalizePhoneFilter;
use Pocta\DataMapper\Attributes\Filters\SlugifyFilter;
use Pocta\DataMapper\Attributes\Filters\StringToLowerFilter;
use Pocta\DataMapper\Attributes\Filters\StringToUpperFilter;
use Pocta\DataMapper\Attributes\Filters\StringTrimFilter;
use Pocta\DataMapper\Attributes\Filters\UniqueArrayFilter;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;

/**
 * Integration tests for data-mapper filters through DataMapperSerializer.
 * Tests that filters correctly transform data during deserialization.
 */
final class FiltersIntegrationTest extends TestCase
{

	private DataMapperSerializer $serializer;

	protected function setUp(): void
	{
		$this->serializer = new DataMapperSerializer();
	}

	#[Test]
	public function stringTrimFilter(): void
	{
		$json = '{"name": "  John Doe  "}';

		$result = $this->serializer->deserialize($json, DtoWithTrim::class);

		self::assertSame('John Doe', $result->name);
	}

	#[Test]
	public function stringToLowerFilter(): void
	{
		$json = '{"code": "ABC123"}';

		$result = $this->serializer->deserialize($json, DtoWithLowerCase::class);

		self::assertSame('abc123', $result->code);
	}

	#[Test]
	public function stringToUpperFilter(): void
	{
		$json = '{"code": "abc123"}';

		$result = $this->serializer->deserialize($json, DtoWithUpperCase::class);

		self::assertSame('ABC123', $result->code);
	}

	#[Test]
	public function normalizeEmailFilter(): void
	{
		$json = '{"email": "  John.Doe@EXAMPLE.COM  "}';

		$result = $this->serializer->deserialize($json, DtoWithNormalizedEmail::class);

		self::assertSame('john.doe@example.com', $result->email);
	}

	#[Test]
	public function normalizePhoneFilter(): void
	{
		$json = '{"phone": "+1 (555) 123-4567"}';

		$result = $this->serializer->deserialize($json, DtoWithNormalizedPhone::class);

		self::assertSame('15551234567', $result->phone);
	}

	#[Test]
	public function normalizePhoneFilterKeepPlus(): void
	{
		$json = '{"phone": "+420 123 456 789"}';

		$result = $this->serializer->deserialize($json, DtoWithNormalizedPhoneKeepPlus::class);

		self::assertSame('+420123456789', $result->phone);
	}

	#[Test]
	public function slugifyFilter(): void
	{
		$json = '{"slug": "Hello World! This is a Test"}';

		$result = $this->serializer->deserialize($json, DtoWithSlugify::class);

		self::assertSame('hello-world-this-is-a-test', $result->slug);
	}

	#[Test]
	public function defaultValueFilter(): void
	{
		$json = '{"status": null}';

		$result = $this->serializer->deserialize($json, DtoWithDefaultValue::class);

		self::assertSame('draft', $result->status);
	}

	#[Test]
	public function defaultValueFilterDoesNotOverrideProvided(): void
	{
		$json = '{"status": "published"}';

		$result = $this->serializer->deserialize($json, DtoWithDefaultValue::class);

		self::assertSame('published', $result->status);
	}

	#[Test]
	public function chainedFilters(): void
	{
		$json = '{"name": "  JOHN DOE  "}';

		$result = $this->serializer->deserialize($json, DtoWithChainedFilters::class);

		// First trim, then lowercase
		self::assertSame('john doe', $result->name);
	}

	#[Test]
	public function eachFilterOnArray(): void
	{
		$json = '{"tags": ["  PHP  ", "  JavaScript  ", "  TypeScript  "]}';

		$result = $this->serializer->deserialize($json, DtoWithEachFilter::class);

		self::assertSame(['PHP', 'JavaScript', 'TypeScript'], $result->tags);
	}

	#[Test]
	public function uniqueArrayFilter(): void
	{
		$json = '{"tags": ["php", "javascript", "php", "typescript", "javascript"]}';

		$result = $this->serializer->deserialize($json, DtoWithUniqueArray::class);

		self::assertSame(['php', 'javascript', 'typescript'], $result->tags);
	}

	#[Test]
	public function trimAndUniqueArrayFilters(): void
	{
		$json = '{"tags": ["  PHP  ", "  JavaScript  ", "  PHP  ", "  TypeScript  "]}';

		$result = $this->serializer->deserialize($json, DtoWithTrimAndUniqueArray::class);

		// Trim each, then unique
		self::assertSame(['PHP', 'JavaScript', 'TypeScript'], $result->tags);
	}

	#[Test]
	public function filtersWithNullValue(): void
	{
		$json = '{"name": null}';

		$result = $this->serializer->deserialize($json, DtoWithNullableFilter::class);

		// Filters should be null-safe
		self::assertNull($result->name);
	}

	#[Test]
	public function multipleFieldsWithDifferentFilters(): void
	{
		$json = '{
			"username": "  JohnDoe123  ",
			"email": "  JOHN@EXAMPLE.COM  ",
			"phone": "+1 (555) 123-4567",
			"slug": "My Article Title"
		}';

		$result = $this->serializer->deserialize($json, DtoWithMultipleFilters::class);

		self::assertSame('JohnDoe123', $result->username);
		self::assertSame('john@example.com', $result->email);
		self::assertSame('15551234567', $result->phone);
		self::assertSame('my-article-title', $result->slug);
	}

}

// Test DTOs

class DtoWithTrim
{

	public function __construct(
		#[StringTrimFilter]
		public string $name,
	)
	{
	}

}

class DtoWithLowerCase
{

	public function __construct(
		#[StringToLowerFilter]
		public string $code,
	)
	{
	}

}

class DtoWithUpperCase
{

	public function __construct(
		#[StringToUpperFilter]
		public string $code,
	)
	{
	}

}

class DtoWithNormalizedEmail
{

	public function __construct(
		#[NormalizeEmailFilter]
		public string $email,
	)
	{
	}

}

class DtoWithNormalizedPhone
{

	public function __construct(
		#[NormalizePhoneFilter]
		public string $phone,
	)
	{
	}

}

class DtoWithNormalizedPhoneKeepPlus
{

	public function __construct(
		#[NormalizePhoneFilter(keepPlus: true)]
		public string $phone,
	)
	{
	}

}

class DtoWithSlugify
{

	public function __construct(
		#[SlugifyFilter]
		public string $slug,
	)
	{
	}

}

class DtoWithDefaultValue
{

	public function __construct(
		#[DefaultValueFilter('draft')]
		public ?string $status,
	)
	{
	}

}

class DtoWithChainedFilters
{

	public function __construct(
		#[StringTrimFilter]
		#[StringToLowerFilter]
		public string $name,
	)
	{
	}

}

class DtoWithEachFilter
{

	/**
	 * @param array<string> $tags
	 */
	public function __construct(
		#[EachFilter(StringTrimFilter::class)]
		public array $tags,
	)
	{
	}

}

class DtoWithUniqueArray
{

	/**
	 * @param array<string> $tags
	 */
	public function __construct(
		#[UniqueArrayFilter]
		public array $tags,
	)
	{
	}

}

class DtoWithTrimAndUniqueArray
{

	/**
	 * @param array<string> $tags
	 */
	public function __construct(
		#[EachFilter(StringTrimFilter::class)]
		#[UniqueArrayFilter]
		public array $tags,
	)
	{
	}

}

class DtoWithNullableFilter
{

	public function __construct(
		#[StringTrimFilter]
		public ?string $name,
	)
	{
	}

}

class DtoWithMultipleFilters
{

	public function __construct(
		#[StringTrimFilter]
		public string $username,
		#[NormalizeEmailFilter]
		public string $email,
		#[NormalizePhoneFilter]
		public string $phone,
		#[SlugifyFilter]
		public string $slug,
	)
	{
	}

}
