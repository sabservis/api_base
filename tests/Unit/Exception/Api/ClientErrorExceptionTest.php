<?php declare(strict_types = 1);

namespace Tests\Unit\Exception\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;

final class ClientErrorExceptionTest extends TestCase
{

	#[Test]
	public function defaultCodeIs400(): void
	{
		$exception = new ClientErrorException('Test error');

		self::assertSame(400, $exception->getCode());
	}

	#[Test]
	public function messageIsSet(): void
	{
		$exception = new ClientErrorException('Test error message');

		self::assertSame('Test error message', $exception->getMessage());
	}

	#[Test]
	public function customCodeInRange(): void
	{
		$exception = new ClientErrorException('Not found', 404);

		self::assertSame(404, $exception->getCode());
	}

	#[Test]
	public function accepts400(): void
	{
		$exception = new ClientErrorException('Bad request', 400);

		self::assertSame(400, $exception->getCode());
	}

	#[Test]
	public function accepts499(): void
	{
		$exception = new ClientErrorException('Custom error', 499);

		self::assertSame(499, $exception->getCode());
	}

	#[Test]
	public function rejectsCodeBelow400(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new ClientErrorException('Test', 399);
	}

	#[Test]
	public function rejectsCodeAbove499(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new ClientErrorException('Test', 500);
	}

	#[Test]
	public function usesDefaultMessageWhenEmpty(): void
	{
		$exception = new ClientErrorException('');

		self::assertSame(ClientErrorException::$defaultMessage, $exception->getMessage());
	}

	#[Test]
	public function contextIsPassedThrough(): void
	{
		$context = ['field' => 'email', 'error' => 'Invalid format'];
		$exception = new ClientErrorException('Validation error', 400, null, $context);

		self::assertSame($context, $exception->getContext());
	}

	#[Test]
	public function previousExceptionIsSet(): void
	{
		$previous = new \RuntimeException('Original error');
		$exception = new ClientErrorException('Wrapped error', 400, $previous);

		self::assertSame($previous, $exception->getPrevious());
	}

	#[Test]
	public function withContextSetsContext(): void
	{
		$exception = ClientErrorException::create()
			->withContext(['key' => 'value']);

		self::assertSame(['key' => 'value'], $exception->getContext());
	}

	#[Test]
	public function withMessageSetsMessage(): void
	{
		$exception = ClientErrorException::create()
			->withMessage('Custom message');

		self::assertSame('Custom message', $exception->getMessage());
	}

}
