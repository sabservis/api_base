<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Utils\HeaderSanitizer;

final class HeaderSanitizerTest extends TestCase
{

	#[Test]
	public function sanitizeRemovesCarriageReturn(): void
	{
		self::assertSame('foobar', HeaderSanitizer::sanitize("foo\rbar"));
	}

	#[Test]
	public function sanitizeRemovesLineFeed(): void
	{
		self::assertSame('foobar', HeaderSanitizer::sanitize("foo\nbar"));
	}

	#[Test]
	public function sanitizeRemovesNullByte(): void
	{
		self::assertSame('foobar', HeaderSanitizer::sanitize("foo\x00bar"));
	}

	#[Test]
	public function sanitizeRemovesCRLF(): void
	{
		self::assertSame('foobar', HeaderSanitizer::sanitize("foo\r\nbar"));
	}

	#[Test]
	public function sanitizeKeepsValidContent(): void
	{
		$valid = 'application/json; charset=utf-8';
		self::assertSame($valid, HeaderSanitizer::sanitize($valid));
	}

	#[Test]
	public function sanitizePreventsHeaderInjection(): void
	{
		$malicious = "text/html\r\nSet-Cookie: admin=true";
		self::assertSame('text/htmlSet-Cookie: admin=true', HeaderSanitizer::sanitize($malicious));
	}

	#[Test]
	#[DataProvider('provideHeaderValues')]
	public function sanitizeVariousInputs(string $input, string $expected): void
	{
		self::assertSame($expected, HeaderSanitizer::sanitize($input));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideHeaderValues(): array
	{
		return [
			'empty string' => ['', ''],
			'simple value' => ['Bearer token123', 'Bearer token123'],
			'with spaces' => ['  some value  ', '  some value  '],
			'unicode content' => ['aplikace/json; charset=utf-8', 'aplikace/json; charset=utf-8'],
			'multiple CRLF' => ["a\r\nb\r\nc", 'abc'],
			'mixed newlines' => ["a\rb\nc\r\nd", 'abcd'],
		];
	}

}
