<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Utils\ByteFormatter;

final class ByteFormatterTest extends TestCase
{

	#[Test]
	#[DataProvider('provideByteFormats')]
	public function formatWithVariousValues(int $bytes, string $expected): void
	{
		self::assertSame($expected, ByteFormatter::format($bytes));
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function provideByteFormats(): array
	{
		return [
			'zero bytes' => [0, '0 B'],
			'one byte' => [1, '1 B'],
			'below KB threshold' => [1_023, '1023 B'],
			'exactly 1 KB' => [1_024, '1 KB'],
			'1.5 KB' => [1_536, '1.5 KB'],
			'below MB threshold' => [1_048_575, '1024 KB'],
			'exactly 1 MB' => [1_048_576, '1 MB'],
			'1.5 MB' => [1_572_864, '1.5 MB'],
			'10 MB' => [10_485_760, '10 MB'],
			'rounding to one decimal' => [1_587_200, '1.5 MB'],
		];
	}

	#[Test]
	public function formatRoundsToOneDecimalPlace(): void
	{
		// 1.45 MB = 1520435.2 bytes -> should round to 1.4 MB (PHP round behavior)
		self::assertSame('1.4 MB', ByteFormatter::format(1_520_435));

		// 1.96 MB = 2054881.28 bytes -> should round to 2 MB
		self::assertSame('2 MB', ByteFormatter::format(2_096_128));
	}

}
