<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Utils\ClientIpResolver;

final class ClientIpResolverTest extends TestCase
{

	#[Test]
	public function extractFromForwardedForWithEmptyStringReturnsNull(): void
	{
		self::assertNull(ClientIpResolver::extractFromForwardedFor('', []));
	}

	#[Test]
	public function extractFromForwardedForWithSingleIpNoProxy(): void
	{
		self::assertSame(
			'192.168.1.1',
			ClientIpResolver::extractFromForwardedFor('192.168.1.1', []),
		);
	}

	#[Test]
	#[DataProvider('provideForwardedForChains')]
	public function extractFromForwardedForWithChain(
		string $forwardedFor,
		array $trustedProxies,
		string|null $expected,
	): void
	{
		self::assertSame(
			$expected,
			ClientIpResolver::extractFromForwardedFor($forwardedFor, $trustedProxies),
		);
	}

	/**
	 * @return array<string, array{string, array<string>, string|null}>
	 */
	public static function provideForwardedForChains(): array
	{
		return [
			'single IP without proxy' => [
				'203.0.113.50',
				[],
				'203.0.113.50',
			],
			'chain with trusted proxy at end' => [
				'203.0.113.50, 10.0.0.1',
				['10.0.0.0/8'],
				'203.0.113.50',
			],
			'chain with multiple trusted proxies' => [
				'203.0.113.50, 10.0.0.1, 10.0.0.2',
				['10.0.0.0/8'],
				'203.0.113.50',
			],
			'chain with exact IP match trusted proxy' => [
				'203.0.113.50, 192.168.1.1',
				['192.168.1.1'],
				'203.0.113.50',
			],
			'all IPs trusted returns leftmost' => [
				'10.0.0.1, 10.0.0.2, 10.0.0.3',
				['10.0.0.0/8'],
				'10.0.0.1',
			],
			'whitespace handling' => [
				'  203.0.113.50  ,  10.0.0.1  ',
				['10.0.0.0/8'],
				'203.0.113.50',
			],
			'complex chain with mixed proxies' => [
				'203.0.113.50, 172.16.0.1, 10.0.0.1',
				['10.0.0.0/8', '172.16.0.0/12'],
				'203.0.113.50',
			],
			'untrusted proxy in middle' => [
				'203.0.113.50, 8.8.8.8, 10.0.0.1',
				['10.0.0.0/8'],
				'8.8.8.8',
			],
		];
	}

	#[Test]
	public function extractClientIpFromChainWithEmptyArrayReturnsNull(): void
	{
		self::assertNull(ClientIpResolver::extractClientIpFromChain([], []));
	}

	#[Test]
	public function extractClientIpFromChainSkipsTrustedProxies(): void
	{
		$ips = ['203.0.113.50', '10.0.0.1', '10.0.0.2'];
		$trusted = ['10.0.0.0/8'];

		self::assertSame('203.0.113.50', ClientIpResolver::extractClientIpFromChain($ips, $trusted));
	}

	#[Test]
	public function extractClientIpFromChainReturnsLeftmostWhenAllTrusted(): void
	{
		$ips = ['10.0.0.1', '10.0.0.2', '10.0.0.3'];
		$trusted = ['10.0.0.0/8'];

		self::assertSame('10.0.0.1', ClientIpResolver::extractClientIpFromChain($ips, $trusted));
	}

}
