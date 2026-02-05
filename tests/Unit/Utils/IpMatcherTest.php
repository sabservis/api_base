<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Utils\IpMatcher;

final class IpMatcherTest extends TestCase
{

	#[Test]
	public function isValidWithValidIPv4(): void
	{
		self::assertTrue(IpMatcher::isValid('192.168.1.1'));
		self::assertTrue(IpMatcher::isValid('10.0.0.1'));
		self::assertTrue(IpMatcher::isValid('255.255.255.255'));
		self::assertTrue(IpMatcher::isValid('0.0.0.0'));
	}

	#[Test]
	public function isValidWithValidIPv6(): void
	{
		self::assertTrue(IpMatcher::isValid('::1'));
		self::assertTrue(IpMatcher::isValid('2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
		self::assertTrue(IpMatcher::isValid('fe80::1'));
	}

	#[Test]
	public function isValidWithInvalidIp(): void
	{
		self::assertFalse(IpMatcher::isValid('invalid'));
		self::assertFalse(IpMatcher::isValid('256.1.1.1'));
		self::assertFalse(IpMatcher::isValid('192.168.1'));
		self::assertFalse(IpMatcher::isValid(''));
	}

	#[Test]
	#[DataProvider('provideCidrMatches')]
	public function matchesCidrWithVariousInputs(string $ip, string $cidr, bool $expected): void
	{
		self::assertSame($expected, IpMatcher::matchesCidr($ip, $cidr));
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function provideCidrMatches(): array
	{
		return [
			'exact /32 match' => ['192.168.1.1', '192.168.1.1/32', true],
			'class C match' => ['192.168.1.100', '192.168.1.0/24', true],
			'class C no match' => ['192.168.2.1', '192.168.1.0/24', false],
			'class B match' => ['192.168.50.1', '192.168.0.0/16', true],
			'class B no match' => ['192.169.1.1', '192.168.0.0/16', false],
			'class A match' => ['10.50.100.150', '10.0.0.0/8', true],
			'class A no match' => ['11.0.0.1', '10.0.0.0/8', false],
			'invalid IP' => ['invalid', '192.168.0.0/24', false],
			'invalid CIDR subnet' => ['192.168.1.1', 'invalid/24', false],
			'no slash in cidr' => ['192.168.1.1', '192.168.1.1', false],
		];
	}

	#[Test]
	public function matchesAnyWithExactMatch(): void
	{
		$patterns = ['192.168.1.1', '10.0.0.1'];

		self::assertTrue(IpMatcher::matchesAny('192.168.1.1', $patterns));
		self::assertTrue(IpMatcher::matchesAny('10.0.0.1', $patterns));
		self::assertFalse(IpMatcher::matchesAny('192.168.1.2', $patterns));
	}

	#[Test]
	public function matchesAnyWithCidr(): void
	{
		$patterns = ['192.168.1.0/24', '10.0.0.0/8'];

		self::assertTrue(IpMatcher::matchesAny('192.168.1.50', $patterns));
		self::assertTrue(IpMatcher::matchesAny('10.100.200.50', $patterns));
		self::assertFalse(IpMatcher::matchesAny('172.16.0.1', $patterns));
	}

	#[Test]
	public function matchesAnyWithMixedPatterns(): void
	{
		$patterns = ['192.168.1.100', '10.0.0.0/8'];

		self::assertTrue(IpMatcher::matchesAny('192.168.1.100', $patterns));
		self::assertTrue(IpMatcher::matchesAny('10.50.50.50', $patterns));
		self::assertFalse(IpMatcher::matchesAny('192.168.1.101', $patterns));
	}

	#[Test]
	public function matchesAnyWithEmptyPatterns(): void
	{
		self::assertFalse(IpMatcher::matchesAny('192.168.1.1', []));
	}

	// === IPv6 CIDR Tests ===

	#[Test]
	#[DataProvider('provideIpv6CidrMatches')]
	public function matchesCidrWithIpv6(string $ip, string $cidr, bool $expected): void
	{
		self::assertSame($expected, IpMatcher::matchesCidr($ip, $cidr));
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function provideIpv6CidrMatches(): array
	{
		return [
			// Exact /128 match
			'exact /128 match' => ['2001:db8::1', '2001:db8::1/128', true],
			'exact /128 no match' => ['2001:db8::2', '2001:db8::1/128', false],

			// /64 subnet (common for networks)
			'/64 match' => ['2001:db8:85a3::8a2e:370:7334', '2001:db8:85a3::/64', true],
			'/64 match different host' => ['2001:db8:85a3::1', '2001:db8:85a3::/64', true],
			'/64 no match different subnet' => ['2001:db8:85a4::1', '2001:db8:85a3::/64', false],

			// /48 and /32 prefixes
			'/48 match' => ['2001:db8:85a3:1234::1', '2001:db8:85a3::/48', true],
			'/48 no match' => ['2001:db8:85a4::1', '2001:db8:85a3::/48', false],
			'/32 match' => ['2001:db8:ffff:ffff::1', '2001:db8::/32', true],
			'/32 no match' => ['2001:db9::1', '2001:db8::/32', false],

			// Loopback
			'loopback /128' => ['::1', '::1/128', true],
			'loopback no match' => ['::2', '::1/128', false],

			// Link-local
			'link-local /10 match' => ['fe80::1', 'fe80::/10', true],
			'link-local /10 match 2' => ['fe80:1234:5678::abcd', 'fe80::/10', true],
			'link-local /10 no match' => ['fe00::1', 'fe80::/10', false],

			// Full address notation
			'full notation match' => [
				'2001:0db8:0000:0000:0000:0000:0000:0001',
				'2001:db8::/32',
				true,
			],

			// Invalid inputs
			'invalid IPv6' => ['invalid', '2001:db8::/32', false],
			'invalid CIDR subnet' => ['2001:db8::1', 'invalid/64', false],
		];
	}

	#[Test]
	public function matchesAnyWithIpv6Patterns(): void
	{
		$patterns = ['2001:db8::/32', '::1'];

		self::assertTrue(IpMatcher::matchesAny('2001:db8:1234::5678', $patterns));
		self::assertTrue(IpMatcher::matchesAny('::1', $patterns));
		self::assertFalse(IpMatcher::matchesAny('2001:db9::1', $patterns));
	}

	#[Test]
	public function matchesAnyWithMixedIpv4AndIpv6(): void
	{
		$patterns = ['192.168.1.0/24', '2001:db8::/32', '::1'];

		// IPv4 matches
		self::assertTrue(IpMatcher::matchesAny('192.168.1.100', $patterns));
		self::assertFalse(IpMatcher::matchesAny('192.168.2.1', $patterns));

		// IPv6 matches
		self::assertTrue(IpMatcher::matchesAny('2001:db8::1', $patterns));
		self::assertTrue(IpMatcher::matchesAny('::1', $patterns));
		self::assertFalse(IpMatcher::matchesAny('2001:db9::1', $patterns));
	}

}
