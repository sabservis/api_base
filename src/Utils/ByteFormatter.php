<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use function round;

/**
 * Utility for formatting byte sizes to human-readable format.
 */
final class ByteFormatter
{

	private const BYTES_PER_KB = 1_024;

	private const BYTES_PER_MB = 1_024 * 1_024;

	/**
	 * Format bytes to human-readable string (B, KB, MB).
	 */
	public static function format(int $bytes): string
	{
		if ($bytes >= self::BYTES_PER_MB) {
			return round($bytes / self::BYTES_PER_MB, 1) . ' MB';
		}

		if ($bytes >= self::BYTES_PER_KB) {
			return round($bytes / self::BYTES_PER_KB, 1) . ' KB';
		}

		return $bytes . ' B';
	}

}
