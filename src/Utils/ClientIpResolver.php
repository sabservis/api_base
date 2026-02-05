<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use function array_map;
use function count;
use function explode;
use function trim;

/**
 * Utility for extracting client IP from X-Forwarded-For header chain.
 */
final class ClientIpResolver
{

	/**
	 * Extract client IP from X-Forwarded-For header value.
	 *
	 * @param string $forwardedFor X-Forwarded-For header value
	 * @param array<string> $trustedProxies List of trusted proxy IPs or CIDR ranges
	 * @return string|null Client IP or null if header is empty
	 */
	public static function extractFromForwardedFor(
		string $forwardedFor,
		array $trustedProxies,
	): string|null
	{
		if ($forwardedFor === '') {
			return null;
		}

		$ips = array_map('trim', explode(',', $forwardedFor));

		return self::extractClientIpFromChain($ips, $trustedProxies);
	}

	/**
	 * Extract client IP from IP chain.
	 *
	 * Traverses the IP chain from right to left (closest proxy first),
	 * returning the first IP that is not a trusted proxy.
	 *
	 * @param array<string> $ips List of IPs from X-Forwarded-For
	 * @param array<string> $trustedProxies List of trusted proxy IPs or CIDR ranges
	 * @return string|null Client IP or null if chain is empty
	 */
	public static function extractClientIpFromChain(
		array $ips,
		array $trustedProxies,
	): string|null
	{
		$count = count($ips);

		for ($i = $count - 1; $i >= 0; $i--) {
			$ip = trim($ips[$i]);

			if (IpMatcher::matchesAny($ip, $trustedProxies)) {
				continue;
			}

			return $ip;
		}

		return $ips[0] ?? null;
	}

}
