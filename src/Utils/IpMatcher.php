<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use function explode;
use function filter_var;
use function inet_pton;
use function ip2long;
use function ord;
use function preg_match;
use function str_contains;
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
	 * Regex pattern for validating IPv4 addresses.
	 */
	private const IPV4_PATTERN = '/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/';

	/**
	 * Regex pattern for validating IPv6 addresses (simplified).
	 */
	private const IPV6_PATTERN = '/^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$|^::(?:[0-9a-fA-F]{1,4}:){0,6}[0-9a-fA-F]{1,4}$|^(?:[0-9a-fA-F]{1,4}:){1,7}:$|^(?:[0-9a-fA-F]{1,4}:){0,6}::(?:[0-9a-fA-F]{1,4}:){0,5}[0-9a-fA-F]{1,4}$/';

	/**
	 * Validate that a string is a valid IP address (IPv4 or IPv6).
	 */
	public static function isValid(string $ip): bool
	{
		// Check IPv4
		if (preg_match(self::IPV4_PATTERN, $ip) === 1) {
			return true;
		}

		// Check IPv6 (simplified pattern)
		if (preg_match(self::IPV6_PATTERN, $ip) === 1) {
			return true;
		}

		// Use filter_var as fallback for edge cases
		return filter_var($ip, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Check if IP address matches a CIDR notation.
	 *
	 * Supports both IPv4 (e.g., 192.168.1.0/24) and IPv6 (e.g., 2001:db8::/32).
	 */
	public static function matchesCidr(string $ip, string $cidr): bool
	{
		if (!str_contains($cidr, '/')) {
			return false;
		}

		[$subnet, $bits] = explode('/', $cidr, 2);
		$bitsInt = (int) $bits;

		// Detect IP version and use appropriate matching
		if (self::isIpv4($ip) && self::isIpv4($subnet)) {
			return self::matchesCidrV4($ip, $subnet, $bitsInt);
		}

		if (self::isIpv6($ip) && self::isIpv6($subnet)) {
			return self::matchesCidrV6($ip, $subnet, $bitsInt);
		}

		// Mixed IP versions or invalid IPs
		return false;
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
		foreach ($patterns as $pattern) {
			// Exact match
			if ($ip === $pattern) {
				return true;
			}

			// CIDR notation match
			if (str_contains($pattern, '/') && self::matchesCidr($ip, $pattern)) {
				return true;
			}
		}

		return false;
	}

}
