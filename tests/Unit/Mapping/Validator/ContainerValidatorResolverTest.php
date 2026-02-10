<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping\Validator;

use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Validation\ConstraintValidatorInterface;
use Sabservis\Api\Mapping\Validator\ContainerValidatorResolver;

final class ContainerValidatorResolverTest extends TestCase
{

	#[Test]
	public function resolvesValidatorFromContainer(): void
	{
		$mockValidator = new class implements ConstraintValidatorInterface {

			public function validate(mixed $value, object $constraint, object $object): string|null
			{
				return null;
			}

		};

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(TestConstraintValidator::class)
			->willReturn($mockValidator);

		$resolver = new ContainerValidatorResolver($container);
		$result = $resolver->resolve(TestConstraintValidator::class);

		self::assertSame($mockValidator, $result);
	}

	#[Test]
	public function throwsWhenValidatorNotRegisteredInContainer(): void
	{
		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willThrowException(new MissingServiceException());

		$resolver = new ContainerValidatorResolver($container);

		$this->expectException(MissingServiceException::class);

		$resolver->resolve(TestConstraintValidator::class);
	}

}

/**
 * @implements ConstraintValidatorInterface
 */
class TestConstraintValidator implements ConstraintValidatorInterface
{

	public function validate(mixed $value, object $constraint, object $object): string|null
	{
		return null;
	}

}
