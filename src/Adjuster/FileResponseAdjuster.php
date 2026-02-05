<?php declare(strict_types = 1);

namespace Sabservis\Api\Adjuster;

use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Http\ApiResponse;
use function filesize;
use function fopen;
use function fstat;
use function is_resource;
use function is_string;
use function rawurlencode;

class FileResponseAdjuster
{

	/**
	 * Adjust response for file download.
	 *
	 * @param resource|string $file File handle (resource) or file path (string)
	 */
	public static function adjust(
		ApiResponse $response,
		mixed $file,
		string $filename,
		string $contentType = 'application/octet-stream',
		bool $forceDownload = true,
	): ApiResponse
	{
		if (is_resource($file)) {
			$handle = $file;
			$size = fstat($handle)['size'] ?? null;
		} elseif (is_string($file)) {
			$handle = fopen($file, 'rb');

			if ($handle === false) {
				throw new RuntimeStateException("Cannot open file: $file");
			}

			$size = filesize($file);
		} else {
			throw new RuntimeStateException('File must be a resource or string path');
		}

		$response = $response
			->withHeader('Content-Type', $contentType)
			->withHeader('Content-Description', 'File Transfer')
			->withHeader('Content-Transfer-Encoding', 'binary')
			->withHeader(
				'Content-Disposition',
				($forceDownload ? 'attachment' : 'inline')
				. '; filename="' . $filename . '"'
				. '; filename*=utf-8\'\'' . rawurlencode($filename),
			)
			->withHeader('Expires', '0')
			->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
			->withHeader('Pragma', 'public')
			->withBody($handle);

		if ($size !== null && $size !== false) {
			$response = $response->withHeader('Content-Length', (string) $size);
		}

		return $response;
	}

}
