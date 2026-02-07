<?php declare(strict_types = 1);

namespace Tests\Integration\DataMapper;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Attributes\MapProperty;
use Pocta\DataMapper\MapperOptions;
use Pocta\DataMapper\Validation\Email;
use Pocta\DataMapper\Validation\Length;
use Pocta\DataMapper\Validation\NotNull;
use Pocta\DataMapper\Validation\Positive;
use Pocta\DataMapper\Validation\Range;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;

/**
 * Integration tests for data-mapper validation through DataMapperSerializer.
 * Tests that validation errors are correctly propagated to API responses.
 */
final class ValidationIntegrationTest extends TestCase
{

	private DataMapperSerializer $serializer;

	protected function setUp(): void
	{
		$this->serializer = new DataMapperSerializer(
			MapperOptions::withAutoValidation(),
		);
	}

	#[Test]
	public function notNullValidation(): void
	{
		$json = '{"name": null, "email": "test@example.com"}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithNotNull::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);
		self::assertSame(422, $exception->getCode());

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		self::assertArrayHasKey('name', $context['validation']);
	}

	#[Test]
	public function emailValidation(): void
	{
		$json = '{"name": "John", "email": "not-an-email"}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithEmail::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		self::assertArrayHasKey('email', $context['validation']);
	}

	#[Test]
	public function rangeValidation(): void
	{
		$json = '{"age": 5}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithRange::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		self::assertArrayHasKey('age', $context['validation']);
	}

	#[Test]
	public function lengthValidation(): void
	{
		$json = '{"username": "ab"}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithLength::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		self::assertArrayHasKey('username', $context['validation']);
	}

	#[Test]
	public function positiveValidation(): void
	{
		$json = '{"amount": -100}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, PaymentWithPositive::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		self::assertArrayHasKey('amount', $context['validation']);
	}

	#[Test]
	public function missingRequiredNestedProperty(): void
	{
		// When a required property is missing in nested object, error path includes the path
		$json = '{"name": "John", "address": {"city": "Prague"}}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithAddressRequired::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		// Error path should include nested path
		self::assertArrayHasKey('address.street', $context['validation']);
	}

	#[Test]
	public function missingRequiredInArrayShowsIndex(): void
	{
		// When a required property is missing in array item, error path includes index
		$json = '{
			"name": "John",
			"addresses": [
				{"street": "Main St", "city": "Prague"},
				{"city": "Brno"},
				{"street": "Oak Ave", "city": "Ostrava"}
			]
		}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithAddressesRequired::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		// Error path should show the array index [1]
		self::assertArrayHasKey('addresses[1].street', $context['validation']);
	}

	#[Test]
	public function multipleValidationErrors(): void
	{
		$json = '{"name": "", "email": "invalid", "age": 5}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, UserWithMultipleValidations::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);

		// Should have errors for all fields
		self::assertArrayHasKey('name', $context['validation']);
		self::assertArrayHasKey('email', $context['validation']);
		self::assertArrayHasKey('age', $context['validation']);
	}

	#[Test]
	public function validDataPassesValidation(): void
	{
		$json = '{"name": "John", "email": "john@example.com"}';

		$result = $this->serializer->deserialize($json, UserWithEmail::class);

		self::assertInstanceOf(UserWithEmail::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame('john@example.com', $result->email);
	}

	#[Test]
	public function deepNestedMissingRequired(): void
	{
		// Test deeply nested missing required property
		$json = '{
			"company": {
				"name": "Acme",
				"departments": [
					{
						"name": "IT",
						"employees": [
							{"name": "John", "email": "john@example.com"},
							{"name": "Jane"}
						]
					}
				]
			}
		}';

		$exception = null;

		try {
			$this->serializer->deserialize($json, OrganizationDto::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		// Should show full path to the error
		self::assertArrayHasKey('company.departments[0].employees[1].email', $context['validation']);
	}

}

// Test DTOs
// phpcs:disable SlevomatCodingStandard.Attributes.AttributeAndTargetSpacing.IncorrectLinesCountBetweenAttributeAndTarget

class UserWithNotNull
{

	public function __construct(
		#[NotNull]
		public string|null $name,
		public string $email,
	)
	{
	}

}

class UserWithEmail
{

	public function __construct(
		public string $name,
		#[Email]
		public string $email,
	)
	{
	}

}

class UserWithRange
{

	public function __construct(#[Range(min: 18, max: 120)] public int $age)
	{
	}

}

class UserWithLength
{

	public function __construct(
		#[Length(min: 3, max: 20)]
		public string $username,
	)
	{
	}

}

class PaymentWithPositive
{

	public function __construct(#[Positive] public float $amount)
	{
	}

}

// phpcs:enable SlevomatCodingStandard.Attributes.AttributeAndTargetSpacing.IncorrectLinesCountBetweenAttributeAndTarget

class AddressDto
{

	public function __construct(
		#[NotNull]
		#[Length(min: 1)]
		public string $street,
		public string $city,
	)
	{
	}

}

class UserWithAddress
{

	public function __construct(
		public string $name,
		public AddressDto $address,
	)
	{
	}

}

class UserWithAddresses
{

	/**
	 * @param array<AddressDto> $addresses
	 */
	public function __construct(
		public string $name,
		#[MapProperty(arrayOf: AddressDto::class)]
		public array $addresses,
	)
	{
	}

}

class AddressRequiredDto
{

	public function __construct(
		public string $street, // Required - no default
		public string $city,
	)
	{
	}

}

class UserWithAddressRequired
{

	public function __construct(
		public string $name,
		public AddressRequiredDto $address,
	)
	{
	}

}

class UserWithAddressesRequired
{

	/**
	 * @param array<AddressRequiredDto> $addresses
	 */
	public function __construct(
		public string $name,
		#[MapProperty(arrayOf: AddressRequiredDto::class)]
		public array $addresses,
	)
	{
	}

}

class UserWithMultipleValidations
{

	public function __construct(
		#[NotNull]
		#[Length(min: 1)]
		public string $name,
		#[Email]
		public string $email,
		#[Range(min: 18)]
		public int $age,
	)
	{
	}

}

class EmployeeDto
{

	public function __construct(
		public string $name,
		#[Email]
		public string $email,
	)
	{
	}

}

class DepartmentDto
{

	/**
	 * @param array<EmployeeDto> $employees
	 */
	public function __construct(
		public string $name,
		#[MapProperty(arrayOf: EmployeeDto::class)]
		public array $employees,
	)
	{
	}

}

class CompanyDto
{

	/**
	 * @param array<DepartmentDto> $departments
	 */
	public function __construct(
		public string $name,
		#[MapProperty(arrayOf: DepartmentDto::class)]
		public array $departments,
	)
	{
	}

}

class OrganizationDto
{

	public function __construct(public CompanyDto $company)
	{
	}

}
