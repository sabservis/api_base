<?php declare(strict_types = 1);

namespace Tests\Unit\Exception;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabservis\Api\Exception\ExceptionExtra;

final class ExceptionExtraTest extends TestCase
{

	#[Test]
	public function createReturnsNewInstance(): void
	{
		$exception = TestExtraException::create();

		self::assertInstanceOf(TestExtraException::class, $exception);
	}

	#[Test]
	public function withMessageSetsStringMessage(): void
	{
		$exception = TestExtraException::create()
			->withMessage('Test error message');

		self::assertSame('Test error message', $this->getMessageProperty($exception));
	}

	#[Test]
	public function withMessageSetsArrayMessage(): void
	{
		$exception = TestExtraException::create()
			->withMessage(['Error 1', 'Error 2']);

		self::assertSame(['Error 1', 'Error 2'], $this->getMessageProperty($exception));
	}

	#[Test]
	public function withContextSetsContext(): void
	{
		$exception = TestExtraException::create()
			->withContext(['key' => 'value']);

		self::assertSame(['key' => 'value'], $exception->getContext());
	}

	#[Test]
	public function withTypedContextSetsTypedContext(): void
	{
		$exception = TestExtraException::create()
			->withTypedContext('validation', ['field' => 'email', 'error' => 'invalid']);

		self::assertSame(
			['validation' => ['field' => 'email', 'error' => 'invalid']],
			$exception->getContext(),
		);
	}

	#[Test]
	public function getContextReturnsNullByDefault(): void
	{
		$exception = TestExtraException::create();

		self::assertNull($exception->getContext());
	}

	#[Test]
	public function methodsChainingWorks(): void
	{
		$exception = TestExtraException::create()
			->withMessage('Error occurred')
			->withContext(['debug' => true]);

		self::assertSame('Error occurred', $this->getMessageProperty($exception));
		self::assertSame(['debug' => true], $exception->getContext());
	}

	#[Test]
	public function withContextAcceptsMixedTypes(): void
	{
		$exception1 = TestExtraException::create()->withContext('string context');
		$exception2 = TestExtraException::create()->withContext(123);
		$exception3 = TestExtraException::create()->withContext(true);
		$exception4 = TestExtraException::create()->withContext(null);

		self::assertSame('string context', $exception1->getContext());
		self::assertSame(123, $exception2->getContext());
		self::assertTrue($exception3->getContext());
		self::assertNull($exception4->getContext());
	}

	/**
	 * @return string|array<string>
	 */
	private function getMessageProperty(TestExtraException $exception): string|array
	{
		$property = new ReflectionProperty(Exception::class, 'message');

		return $property->getValue($exception);
	}

}

class TestExtraException extends Exception
{

	use ExceptionExtra;

}
