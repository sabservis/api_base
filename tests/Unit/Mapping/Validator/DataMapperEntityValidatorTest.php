<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping\Validator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Validation\Email;
use Pocta\DataMapper\Validation\NotBlank;
use Pocta\DataMapper\Validation\Valid;
use Pocta\DataMapper\Validation\Validator;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Mapping\Validator\DataMapperEntityValidator;

final class DataMapperEntityValidatorTest extends TestCase
{

	private DataMapperEntityValidator $entityValidator;

	protected function setUp(): void
	{
		$this->entityValidator = new DataMapperEntityValidator(new Validator());
	}

	#[Test]
	public function validEntityDoesNotThrow(): void
	{
		$entity = new ValidTestDto();
		$entity->name = 'John';
		$entity->email = 'john@example.com';

		$this->entityValidator->validate($entity);

		$this->expectNotToPerformAssertions();
	}

	#[Test]
	public function invalidEntityThrowsValidationException(): void
	{
		$entity = new ValidTestDto();
		$entity->name = '';
		$entity->email = 'not-an-email';

		$this->expectException(ValidationException::class);

		$this->entityValidator->validate($entity);
	}

	#[Test]
	public function errorsAreWrappedInArrays(): void
	{
		$entity = new ValidTestDto();
		$entity->name = '';
		$entity->email = 'not-an-email';

		try {
			$this->entityValidator->validate($entity);
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$context = $e->getContext();
			self::assertIsArray($context);
			self::assertArrayHasKey('validation', $context);

			$validation = $context['validation'];
			self::assertArrayHasKey('name', $validation);
			self::assertArrayHasKey('email', $validation);

			// Each field must have array of messages
			self::assertIsArray($validation['name']);
			self::assertIsArray($validation['email']);
			self::assertNotEmpty($validation['name']);
			self::assertNotEmpty($validation['email']);
		}
	}

	#[Test]
	public function uninitializedPropertyWithAssertReturnsRequiredError(): void
	{
		$entity = new UninitializedTestDto();

		try {
			$this->entityValidator->validate($entity);
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$context = $e->getContext();
			$validation = $context['validation'];

			self::assertArrayHasKey('name', $validation);
			self::assertSame(['This field is required.'], $validation['name']);
		}
	}

	#[Test]
	public function validationGroupsArePassedThrough(): void
	{
		$entity = new GroupedTestDto();
		$entity->name = '';
		$entity->email = '';

		// Only 'create' group - should validate name (group: create) but not email (group: update)
		try {
			$this->entityValidator->validate($entity, 'create');
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$context = $e->getContext();
			$validation = $context['validation'];

			self::assertArrayHasKey('name', $validation);
			self::assertArrayNotHasKey('email', $validation);
		}
	}

	#[Test]
	public function validationGroupsAsArrayArePassedThrough(): void
	{
		$entity = new GroupedTestDto();
		$entity->name = '';
		$entity->email = '';

		// Both groups - should validate both
		try {
			$this->entityValidator->validate($entity, ['create', 'update']);
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$context = $e->getContext();
			$validation = $context['validation'];

			self::assertArrayHasKey('name', $validation);
			self::assertArrayHasKey('email', $validation);
		}
	}

	#[Test]
	public function nestedValidationUsesPathNotation(): void
	{
		$entity = new ParentTestDto();
		$entity->child = new ValidTestDto();
		$entity->child->name = '';
		$entity->child->email = 'not-an-email';

		try {
			$this->entityValidator->validate($entity);
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$context = $e->getContext();
			$validation = $context['validation'];

			self::assertArrayHasKey('child.name', $validation);
			self::assertArrayHasKey('child.email', $validation);
		}
	}

	#[Test]
	public function nullGroupsDefaultsToDefaultGroup(): void
	{
		$entity = new ValidTestDto();
		$entity->name = '';
		$entity->email = 'not-an-email';

		// null groups should default to 'Default' group which NotBlank/Email use
		$this->expectException(ValidationException::class);

		$this->entityValidator->validate($entity, null);
	}

	#[Test]
	public function exceptionMessageIsCorrect(): void
	{
		$entity = new ValidTestDto();
		$entity->name = '';
		$entity->email = 'not-an-email';

		try {
			$this->entityValidator->validate($entity);
			self::fail('Expected ValidationException');
		} catch (ValidationException $e) {
			self::assertSame('Request body contains an error. See context for details.', $e->getMessage());
			self::assertSame(422, $e->getCode());
		}
	}

}

class ValidTestDto
{

	#[NotBlank]
	public string $name = '';

	#[Email]
	public string $email = '';

}

class UninitializedTestDto
{

	#[NotBlank]
	public string $name;

}

class GroupedTestDto
{

	#[NotBlank(groups: ['create'])]
	public string $name = '';

	#[NotBlank(groups: ['update'])]
	public string $email = '';

}

class ParentTestDto
{

	#[Valid]
	public ValidTestDto $child;

}
