<?php declare(strict_types = 1);

namespace Tests\Unit\Adjuster;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Adjuster\FileResponseAdjuster;
use Sabservis\Api\Http\ApiResponse;
use function fopen;
use function fwrite;
use function rewind;
use function is_resource;
use function sys_get_temp_dir;
use function tempnam;
use function file_put_contents;
use function unlink;

final class FileResponseAdjusterTest extends TestCase
{

	#[Test]
	public function responseWithResource(): void
	{
		$response = new ApiResponse();

		$stream = fopen('php://temp', 'r+b');
		fwrite($stream, 'test content');
		rewind($stream);

		$response = FileResponseAdjuster::adjust($response, $stream, 'filename.txt');

		self::assertSame('application/octet-stream', $response->getHeader('content-type'));
		self::assertSame('File Transfer', $response->getHeader('content-description'));
		self::assertSame('binary', $response->getHeader('content-transfer-encoding'));
		self::assertSame('attachment; filename="filename.txt"; filename*=utf-8\'\'filename.txt', $response->getHeader('content-disposition'));
		self::assertSame('0', $response->getHeader('expires'));
		self::assertSame('must-revalidate, post-check=0, pre-check=0', $response->getHeader('cache-control'));
		self::assertSame('public', $response->getHeader('pragma'));
		self::assertSame('12', $response->getHeader('content-length'));
		self::assertTrue(is_resource($response->getBody()));
	}

	#[Test]
	public function responseWithFilePath(): void
	{
		$response = new ApiResponse();

		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test file content');

		try {
			$response = FileResponseAdjuster::adjust($response, $tempFile, 'download.txt', 'text/plain', false);

			self::assertSame('text/plain', $response->getHeader('content-type'));
			self::assertSame('inline; filename="download.txt"; filename*=utf-8\'\'download.txt', $response->getHeader('content-disposition'));
			self::assertSame('17', $response->getHeader('content-length'));
			self::assertTrue(is_resource($response->getBody()));
		} finally {
			unlink($tempFile);
		}
	}

}
