<?php declare(strict_types = 1);

namespace Tests\Integration\DataMapper;

use Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\MapperOptions;
use Pocta\DataMapper\Validation\ConstraintInterface;
use Pocta\DataMapper\Validation\ConstraintValidatorInterface;
use Pocta\DataMapper\Validation\ValidatorResolverInterface;
use RuntimeException;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;

/**
 * Integration tests for ValidatorResolver support in DataMapperSerializer.
 * Tests that custom constraint validators are resolved via the provided resolver.
 */
final class ValidatorResolverIntegrationTest extends TestCase
{

	#[Test]
	public function customConstraintValidatorIsResolvedViaResolver(): void
	{
		$validator = new AlwaysFailsValidator('Resolved via DI');

		$resolver = new InMemoryValidatorResolver([
			AlwaysFailsValidator::class => $validator,
		]);

		$serializer = new DataMapperSerializer(
			MapperOptions::withAutoValidation(),
			$resolver,
		);

		$exception = null;

		try {
			$serializer->deserialize('{"value":"test"}', DtoWithCustomConstraint::class);
		} catch (ValidationException $e) {
			$exception = $e;
		}

		self::assertNotNull($exception);

		$context = $exception->getContext();
		self::assertArrayHasKey('validation', $context);
		self::assertArrayHasKey('value', $context['validation']);
		self::assertSame('Resolved via DI', $context['validation']['value']);
	}

	#[Test]
	public function validDataPassesCustomConstraintValidation(): void
	{
		$validator = new AlwaysPassesValidator();

		$resolver = new InMemoryValidatorResolver([
			AlwaysFailsValidator::class => $validator,
		]);

		$serializer = new DataMapperSerializer(
			MapperOptions::withAutoValidation(),
			$resolver,
		);

		$result = $serializer->deserialize('{"value":"test"}', DtoWithCustomConstraint::class);

		self::assertInstanceOf(DtoWithCustomConstraint::class, $result);
		self::assertSame('test', $result->value);
	}

	#[Test]
	public function serializerWorksWithoutResolver(): void
	{
		$serializer = new DataMapperSerializer();

		$result = $serializer->deserialize('{"name":"John"}', SimpleDto::class);

		self::assertInstanceOf(SimpleDto::class, $result);
		self::assertSame('John', $result->name);
	}

	#[Test]
	public function serializerWithNullResolverWorksLikeWithout(): void
	{
		$serializer = new DataMapperSerializer(null, null);

		$result = $serializer->deserialize('{"name":"John"}', SimpleDto::class);

		self::assertInstanceOf(SimpleDto::class, $result);
		self::assertSame('John', $result->name);
	}

}

// Test infrastructure

/**
 * In-memory validator resolver for testing.
 */
class InMemoryValidatorResolver implements ValidatorResolverInterface
{

	/**
	 * @param array<class-string<ConstraintValidatorInterface>, ConstraintValidatorInterface> $validators
	 */
	public function __construct(private readonly array $validators)
	{
	}

	public function resolve(string $validatorClass): ConstraintValidatorInterface
	{
		if (!isset($this->validators[$validatorClass])) {
			throw new RuntimeException("Validator {$validatorClass} not registered");
		}

		return $this->validators[$validatorClass];
	}

}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class AlwaysFailsConstraint implements ConstraintInterface
{

	/** @var array<string> */
	public array $groups = ['Default'];

	/**
	 * @return class-string<ConstraintValidatorInterface>
	 */
	public function validatedBy(): string
	{
		return AlwaysFailsValidator::class;
	}

	public function validate(mixed $value, string $propertyName): string|null
	{
		return null; // Delegated to validatedBy()
	}

}

class AlwaysFailsValidator implements ConstraintValidatorInterface
{

	public function __construct(private readonly string $message = 'Always fails')
	{
	}

	public function validate(mixed $value, object $constraint, object $object): string|null
	{
		return $this->message;
	}

}

class AlwaysPassesValidator implements ConstraintValidatorInterface
{

	public function validate(mixed $value, object $constraint, object $object): string|null
	{
		return null;
	}

}

class DtoWithCustomConstraint
{

	public function __construct(
		#[AlwaysFailsConstraint]
		public readonly string $value,
	)
	{
	}

}

class SimpleDto
{

	public function __construct(public readonly string $name)
	{
	}

}
