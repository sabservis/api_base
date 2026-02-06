<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\Core;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;

final class MiddlewarePriorityTest extends TestCase
{

	#[Test]
	public function defaultPriorityIsTen(): void
	{
		$priority = new MiddlewarePriority();

		self::assertSame(10, $priority->level);
	}

	#[Test]
	public function customPriorityIsStored(): void
	{
		$priority = new MiddlewarePriority(100);

		self::assertSame(100, $priority->level);
	}

}
