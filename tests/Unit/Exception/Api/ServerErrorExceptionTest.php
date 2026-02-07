<?php declare(strict_types = 1);

namespace Tests\Unit\Exception\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;

final class ServerErrorExceptionTest extends TestCase
{

	#[Test]
	public function defaultCodeIs500(): void
	{
		$exception = new ServerErrorException('Test error');

		self::assertSame(500, $exception->getCode());
	}

	#[Test]
	public function messageIsSet(): void
	{
		$exception = new ServerErrorException('Server error message');

		self::assertSame('Server error message', $exception->getMessage());
	}

	#[Test]
	public function customCodeInRange(): void
	{
		$exception = new ServerErrorException('Service unavailable', 503);

		self::assertSame(503, $exception->getCode());
	}

	#[Test]
	public function accepts500(): void
	{
		$exception = new ServerErrorException('Internal error', 500);

		self::assertSame(500, $exception->getCode());
	}

	#[Test]
	public function accepts599(): void
	{
		$exception = new ServerErrorException('Custom error', 599);

		self::assertSame(599, $exception->getCode());
	}

	#[Test]
	public function rejectsCodeBelow500(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new ServerErrorException('Test', 499);
	}

	#[Test]
	public function rejectsCodeAbove599(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new ServerErrorException('Test', 600);
	}

	#[Test]
	public function usesDefaultMessageWhenEmpty(): void
	{
		$exception = new ServerErrorException('');

		self::assertSame(ServerErrorException::$defaultMessage, $exception->getMessage());
	}

	#[Test]
	public function previousExceptionIsSet(): void
	{
		$previous = new RuntimeException('Database connection failed');
		$exception = new ServerErrorException('Database error', 500, $previous);

		self::assertSame($previous, $exception->getPrevious());
	}

	#[Test]
	public function withContextSetsContext(): void
	{
		$exception = ServerErrorException::create()
			->withContext(['service' => 'database']);

		self::assertSame(['service' => 'database'], $exception->getContext());
	}

	#[Test]
	public function withTypedContextSetsTypedContext(): void
	{
		$exception = ServerErrorException::create()
			->withTypedContext('debug', ['sql' => 'SELECT * FROM users']);

		self::assertSame(['debug' => ['sql' => 'SELECT * FROM users']], $exception->getContext());
	}

}
