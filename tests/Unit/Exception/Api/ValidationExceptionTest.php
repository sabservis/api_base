<?php declare(strict_types = 1);

namespace Tests\Unit\Exception\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;

final class ValidationExceptionTest extends TestCase
{

	#[Test]
	public function defaultCodeIs422(): void
	{
		$exception = new ValidationException();

		self::assertSame(422, $exception->getCode());
	}

	#[Test]
	public function defaultMessageIsSet(): void
	{
		$exception = new ValidationException();

		self::assertSame('Request body contains an error. See context for details.', $exception->getMessage());
	}

	#[Test]
	public function customMessageIsSet(): void
	{
		$exception = new ValidationException('Custom validation error');

		self::assertSame('Custom validation error', $exception->getMessage());
	}

	#[Test]
	public function fieldsAreSetAsContext(): void
	{
		$fields = ['email' => ['Invalid format'], 'name' => ['Required']];
		$exception = new ValidationException(fields: $fields);

		self::assertSame($fields, $exception->getContext());
	}

	#[Test]
	public function withFieldsSetsValidationContext(): void
	{
		$fields = ['email' => ['Invalid email format']];
		$exception = (new ValidationException())->withFields($fields);

		self::assertSame(['validation' => $fields], $exception->getContext());
	}

	#[Test]
	public function withFormFieldsSetsValidationContext(): void
	{
		$fields = [
			'email' => ['Invalid email format'],
			'password' => ['Too short', 'Must contain number'],
		];
		$exception = (new ValidationException())->withFormFields($fields);

		self::assertSame(['validation' => $fields], $exception->getContext());
	}

	#[Test]
	public function withFormFieldsRejectsNumericKeys(): void
	{
		$this->expectException(InvalidArgumentException::class);

		(new ValidationException())->withFormFields([
			0 => ['Error message'],
		]);
	}

	#[Test]
	public function withFormFieldsRejectsNonArrayValues(): void
	{
		$this->expectException(InvalidArgumentException::class);

		(new ValidationException())->withFormFields([
			'email' => 'Invalid format', // should be array
		]);
	}

	#[Test]
	public function extendsClientErrorException(): void
	{
		$exception = new ValidationException();

		self::assertInstanceOf(\Sabservis\Api\Exception\Api\ClientErrorException::class, $exception);
	}

	#[Test]
	public function customCodeInClientErrorRange(): void
	{
		$exception = new ValidationException('Error', 400);

		self::assertSame(400, $exception->getCode());
	}

	#[Test]
	public function rejectsServerErrorCode(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new ValidationException('Error', 500);
	}

	#[Test]
	public function previousExceptionIsSet(): void
	{
		$previous = new \RuntimeException('Original error');
		$exception = new ValidationException('Validation failed', 422, $previous);

		self::assertSame($previous, $exception->getPrevious());
	}

	#[Test]
	public function withFieldsReturnsNewInstance(): void
	{
		$original = new ValidationException();
		$modified = $original->withFields(['field' => ['error']]);

		// withFields returns static (same instance modified), not new instance
		self::assertSame($original, $modified);
	}

}
