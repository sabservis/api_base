<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Utils\JsonDecoder;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class JsonDecoderTest extends TestCase
{

	#[Test]
	public function decodesIntegersAtInt64Boundaries(): void
	{
		$decoded = JsonDecoder::decode('{"max": ' . PHP_INT_MAX . ', "min": ' . PHP_INT_MIN . '}', 64);

		self::assertSame(['max' => PHP_INT_MAX, 'min' => PHP_INT_MIN], $decoded);
	}

	#[Test]
	#[DataProvider('provideOverflowingJson')]
	public function rejectsIntegerLiteralBeyondInt64(string $json, string $expectedPath): void
	{
		try {
			JsonDecoder::decode($json, 64);
			self::fail('Expected ClientErrorException');
		} catch (ClientErrorException $e) {
			self::assertSame(400, $e->getCode());
			self::assertStringContainsString('out of range', $e->getMessage());
			self::assertStringContainsString($expectedPath, $e->getMessage());
		}
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provideOverflowingJson(): array
	{
		return [
			'top-level field' => ['{"id": 9223372036854775808}', 'id'],
			'negative' => ['{"id": -9223372036854775809}', 'id'],
			'nested in list' => ['{"items": [{"id": 1}, {"id": 12345678901234567890}]}', 'items.1.id'],
			'bare value' => ['12345678901234567890', '$'],
		];
	}

	#[Test]
	public function floatLiteralsAndNumericStringsAreNotIntegers(): void
	{
		$json = '{"amount": 1e19, "big": 9223372036854775808.0, "text": "9223372036854775808"}';
		$decoded = JsonDecoder::decode($json, 64);

		self::assertSame(1.0e19, $decoded['amount']);
		self::assertSame(9_223_372_036_854_775_808.0, $decoded['big']);
		self::assertSame('9223372036854775808', $decoded['text']);
	}

	#[Test]
	public function invalidJsonThrowsJsonException(): void
	{
		$this->expectException(JsonException::class);

		JsonDecoder::decode('{invalid', 64);
	}

	#[Test]
	public function depthLimitIsApplied(): void
	{
		$this->expectException(JsonException::class);

		JsonDecoder::decode('[[[[1]]]]', 2);
	}

}
