<?php declare(strict_types = 1);

namespace Tests\Unit\Exception;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\LogicalException;

final class LogicalExceptionTest extends TestCase
{

	#[Test]
	public function isBaseTypeForCustomLogicalExceptions(): void
	{
		$exception = new class('Invalid state') extends LogicalException
		{
		};

		self::assertInstanceOf(LogicalException::class, $exception);
		self::assertInstanceOf(LogicException::class, $exception);
		self::assertSame('Invalid state', $exception->getMessage());
	}

}
