<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Utils\Regex;

final class RegexTest extends TestCase
{

	#[Test]
	public function match(): void
	{
		self::assertNull(Regex::match('foo', '#\d+#'));
		self::assertSame(['foo'], Regex::match('foo', '#\w+#'));
		self::assertSame(['foo', 'foo'], Regex::match('foo', '#(\w+)#'));
	}

	#[Test]
	public function replace(): void
	{
		self::assertSame('foo', Regex::replace('foobar', '#bar#', ''));
	}

	#[Test]
	public function replaceCallback(): void
	{
		self::assertSame('barfoo', Regex::replaceCallback('foo', '#(foo)#', static fn ($matches) => 'bar' . $matches[1]));
	}

}
