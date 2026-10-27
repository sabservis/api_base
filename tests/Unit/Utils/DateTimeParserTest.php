<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Utils\DateTimeParser;
use function date_default_timezone_get;

final class DateTimeParserTest extends TestCase
{

	#[Test]
	public function parseIso8601WithTimezone(): void
	{
		$result = DateTimeParser::parse('2024-01-15T10:30:00+02:00');

		self::assertSame('2024-01-15', $result->format('Y-m-d'));
		self::assertSame('10:30:00', $result->format('H:i:s'));
	}

	#[Test]
	public function parseIso8601WithoutTimezone(): void
	{
		$result = DateTimeParser::parse('2024-01-15T10:30:00');

		self::assertSame('2024-01-15', $result->format('Y-m-d'));
		self::assertSame('10:30:00', $result->format('H:i:s'));
	}

	#[Test]
	public function parseDateOnly(): void
	{
		$result = DateTimeParser::parse('2024-01-15');

		self::assertSame('2024-01-15', $result->format('Y-m-d'));
	}

	#[Test]
	public function parseWithFallbackConstructor(): void
	{
		// This format is not in DEFAULT_FORMATS but works with constructor
		$result = DateTimeParser::parse('15 January 2024');

		self::assertSame('2024-01-15', $result->format('Y-m-d'));
	}

	#[Test]
	public function parseWithCustomFormats(): void
	{
		$result = DateTimeParser::parse('15/01/2024', ['d/m/Y']);

		self::assertSame('2024-01-15', $result->format('Y-m-d'));
	}

	#[Test]
	public function parseThrowsOnInvalidValue(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unable to parse datetime value "not-a-date"');

		DateTimeParser::parse('not-a-date');
	}

	#[Test]
	#[DataProvider('provideValidDates')]
	public function parseVariousValidFormats(string $input, string $expectedDate): void
	{
		$result = DateTimeParser::parse($input);

		self::assertSame($expectedDate, $result->format('Y-m-d'));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideValidDates(): array
	{
		return [
			'ISO 8601 full' => ['2024-06-15T14:30:00+00:00', '2024-06-15'],
			'ISO 8601 no tz' => ['2024-06-15T14:30:00', '2024-06-15'],
			'date only' => ['2024-06-15', '2024-06-15'],
			'with Z timezone' => ['2024-06-15T14:30:00Z', '2024-06-15'],
			'relative date' => ['tomorrow', (new DateTimeImmutable('tomorrow'))->format('Y-m-d')],
		];
	}

	#[Test]
	public function defaultFormatsConstant(): void
	{
		self::assertSame([
			'Y-m-d\TH:i:sP',
			'Y-m-d\TH:i:s',
			'!Y-m-d',
		], DateTimeParser::DEFAULT_FORMATS);
	}

	#[Test]
	public function dateOnlyIsMidnight(): void
	{
		$result = DateTimeParser::parse('2024-05-01');

		self::assertSame('2024-05-01 00:00:00.000000', $result->format('Y-m-d H:i:s.u'));
	}

	#[Test]
	public function dateOnlyIsDeterministic(): void
	{
		self::assertEquals(DateTimeParser::parse('2024-05-01'), DateTimeParser::parse('2024-05-01'));
	}

	#[Test]
	public function dateOnlyKeepsDefaultTimezone(): void
	{
		$result = DateTimeParser::parse('2024-05-01');

		self::assertSame(date_default_timezone_get(), $result->getTimezone()->getName());
	}

	#[Test]
	public function isoDateTimeKeepsTime(): void
	{
		self::assertSame('10:30:00.000000', DateTimeParser::parse('2024-05-01T10:30:00')->format('H:i:s.u'));
		self::assertSame('10:30:00 +02:00', DateTimeParser::parse('2024-05-01T10:30:00+02:00')->format('H:i:s P'));
	}

}
