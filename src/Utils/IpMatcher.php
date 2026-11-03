<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use function count;
use function explode;
use function filter_var;
use function inet_ntop;
use function inet_pton;
use function ip2long;
use function ord;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

/**
 * Utility for IP address validation and CIDR matching.
 *
 * Supports both IPv4 and IPv6 addresses and CIDR notation.
 */
final class IpMatcher
{

	/**
	 * Number of bits in IPv4 address.
	 */
	private const IPV4_BITS = 32;

	/**
	 * Number of bits in IPv6 address.
	 */
	private const IPV6_BITS = 128;

	/**
	 * IPv4-mapped IPv6 prefix (::ffff:0:0/96) in binary form.
	 */
	private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

	/**
	 * Validate that a string is a valid IP address (IPv4 or IPv6).
	 *
	 * filter_var() is strict (rejects "1:::2", trailing newline, ...) - a hand-written
	 * regex previously accepted malformed IPv6 strings.
	 */
	public static function isValid(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Canonical textual form for comparison: lowercase compressed IPv6,
	 * IPv4-mapped IPv6 (::ffff:10.0.0.1) collapsed to plain IPv4.
	 * Invalid input is returned unchanged.
	 */
	public static function canonicalize(string $ip): string
	{
		$binary = @inet_pton($ip);

		if ($binary === false) {
			return $ip;
		}

		if (strlen($binary) === 16 && str_starts_with($binary, self::IPV4_MAPPED_PREFIX)) {
			$binary = substr($binary, 12);
		}

		$canonical = inet_ntop($binary);

		return $canonical === false ? $ip : $canonical;
	}

	/**
	 * Check if IP address matches a CIDR notation.
	 *
	 * Supports both IPv4 (e.g., 192.168.1.0/24) and IPv6 (e.g., 2001:db8::/32).
	 */
	public static function matchesCidr(string $ip, string $cidr): bool
	{
		$parsed = self::parseCidr($cidr);

		if ($parsed === null) {
			return false;
		}

		[$subnet, $bits] = $parsed;

		// Detect IP version and use appropriate matching
		if (self::isIpv4($ip) && self::isIpv4($subnet)) {
			return self::matchesCidrV4($ip, $subnet, $bits);
		}

		if (self::isIpv6($ip) && self::isIpv6($subnet)) {
			return self::matchesCidrV6($ip, $subnet, $bits);
		}

		// Mixed IP versions or invalid IPs
		return false;
	}

	/**
	 * First pattern failing isValidPattern(), or null when all are valid.
	 *
	 * @param array<string> $patterns
	 */
	public static function findInvalidPattern(array $patterns): string|null
	{
		foreach ($patterns as $pattern) {
			if (!self::isValidPattern($pattern)) {
				return $pattern;
			}
		}

		return null;
	}

	/**
	 * Validate a trusted-proxy style pattern: exact IP address or CIDR with in-range prefix.
	 */
	public static function isValidPattern(string $pattern): bool
	{
		if (!str_contains($pattern, '/')) {
			return filter_var($pattern, FILTER_VALIDATE_IP) !== false;
		}

		$parsed = self::parseCidr($pattern);

		if ($parsed === null) {
			return false;
		}

		[$subnet, $bits] = $parsed;

		if (self::isIpv4($subnet)) {
			return $bits <= self::IPV4_BITS;
		}

		return self::isIpv6($subnet) && $bits <= self::IPV6_BITS;
	}

	/**
	 * Split CIDR into subnet and prefix length.
	 *
	 * SECURITY: Prefix must be plain digits. Casting e.g. "" or "abc" to int yields 0,
	 * which would turn a config typo into "/0" = match every address.
	 *
	 * @return array{string, int}|null
	 */
	private static function parseCidr(string $cidr): array|null
	{
		$parts = explode('/', $cidr);

		if (count($parts) !== 2 || preg_match('/^[0-9]{1,3}\z/', $parts[1]) !== 1) {
			return null;
		}

		return [$parts[0], (int) $parts[1]];
	}

	/**
	 * Check if string is a valid IPv4 address.
	 */
	private static function isIpv4(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
	}

	/**
	 * Check if string is a valid IPv6 address.
	 */
	private static function isIpv6(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
	}

	/**
	 * IPv4 CIDR matching using ip2long().
	 */
	private static function matchesCidrV4(string $ip, string $subnet, int $bits): bool
	{
		if ($bits < 0 || $bits > self::IPV4_BITS) {
			return false;
		}

		$ipLong = ip2long($ip);
		$subnetLong = ip2long($subnet);

		if ($ipLong === false || $subnetLong === false) {
			return false;
		}

		$mask = -1 << self::IPV4_BITS - $bits;

		return ($ipLong & $mask) === ($subnetLong & $mask);
	}

	/**
	 * IPv6 CIDR matching using inet_pton() and binary comparison.
	 */
	private static function matchesCidrV6(string $ip, string $subnet, int $bits): bool
	{
		if ($bits < 0 || $bits > self::IPV6_BITS) {
			return false;
		}

		$ipBin = inet_pton($ip);
		$subnetBin = inet_pton($subnet);

		if ($ipBin === false || $subnetBin === false) {
			return false;
		}

		// Compare full bytes
		$fullBytes = (int) ($bits / 8);
		if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
			return false;
		}

		// Compare remaining bits in partial byte
		$remainingBits = $bits % 8;
		if ($remainingBits > 0 && $fullBytes < strlen($ipBin)) {
			$mask = 0xFF << 8 - $remainingBits;
			$ipByte = ord($ipBin[$fullBytes]);
			$subnetByte = ord($subnetBin[$fullBytes]);

			if (($ipByte & $mask) !== ($subnetByte & $mask)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if IP matches any of the given patterns.
	 *
	 * Supports exact match and CIDR notation.
	 *
	 * @param array<string> $patterns List of IP addresses or CIDR notations
	 */
	public static function matchesAny(string $ip, array $patterns): bool
	{
		$ip = self::canonicalize($ip);

		foreach ($patterns as $pattern) {
			// CIDR notation match
			if (str_contains($pattern, '/')) {
				if (self::matchesCidr($ip, $pattern)) {
					return true;
				}

				continue;
			}

			// Exact match - canonicalized so "2001:DB8::1" equals "2001:db8::1"
			if ($ip === self::canonicalize($pattern)) {
				return true;
			}
		}

		return false;
	}

}
