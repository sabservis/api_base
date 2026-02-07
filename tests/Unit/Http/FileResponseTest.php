<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Http\FileResponse;
use function basename;
use function file_exists;
use function file_put_contents;
use function mkdir;
use function realpath;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class FileResponseTest extends TestCase
{

	private string $testFile;

	protected function setUp(): void
	{
		$this->testFile = sys_get_temp_dir() . '/test-file-response.pdf';
		file_put_contents($this->testFile, 'PDF content here');
	}

	protected function tearDown(): void
	{
		if (file_exists($this->testFile)) {
			unlink($this->testFile);
		}
	}

	#[Test]
	public function fromPathCreatesResponse(): void
	{
		$response = FileResponse::fromPath($this->testFile);

		self::assertSame('test-file-response.pdf', $response->getFilename());
		self::assertSame('application/pdf', $response->getContentType());
		self::assertSame(16, $response->getSize()); // 'PDF content here' = 16 bytes
		self::assertTrue($response->isForceDownload());
	}

	#[Test]
	public function fromPathWithCustomFilename(): void
	{
		$response = FileResponse::fromPath($this->testFile, 'invoice.pdf');

		self::assertSame('invoice.pdf', $response->getFilename());
	}

	#[Test]
	public function fromPathWithCustomContentType(): void
	{
		$response = FileResponse::fromPath($this->testFile, null, 'application/x-custom');

		self::assertSame('application/x-custom', $response->getContentType());
	}

	#[Test]
	public function fromPathThrowsWhenFileNotFound(): void
	{
		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('File not found');

		FileResponse::fromPath('/nonexistent/file.pdf');
	}

	#[Test]
	public function fromContentCreatesResponse(): void
	{
		$content = 'Binary content';
		$response = FileResponse::fromContent($content, 'data.bin');

		self::assertSame('data.bin', $response->getFilename());
		self::assertSame('application/octet-stream', $response->getContentType());
		self::assertSame(14, $response->getSize());
		self::assertSame($content, $response->getBody());
	}

	#[Test]
	public function fromContentDetectsMimeType(): void
	{
		$response = FileResponse::fromContent('data', 'report.xlsx');

		self::assertSame(
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			$response->getContentType(),
		);
	}

	#[Test]
	public function inlineDisablesForceDownload(): void
	{
		$response = FileResponse::fromPath($this->testFile)->inline();

		self::assertFalse($response->isForceDownload());
	}

	#[Test]
	public function downloadEnablesForceDownload(): void
	{
		$response = FileResponse::fromPath($this->testFile)
			->inline()
			->download();

		self::assertTrue($response->isForceDownload());
	}

	#[Test]
	public function withContentTypeChangesType(): void
	{
		$response = FileResponse::fromPath($this->testFile)
			->withContentType('application/x-custom');

		self::assertSame('application/x-custom', $response->getContentType());
	}

	#[Test]
	public function withFilenameChangesName(): void
	{
		$response = FileResponse::fromPath($this->testFile)
			->withFilename('new-name.pdf');

		self::assertSame('new-name.pdf', $response->getFilename());
	}

	#[Test]
	public function toApiResponseSetsHeaders(): void
	{
		$response = FileResponse::fromPath($this->testFile, 'report.pdf')
			->toApiResponse();

		self::assertSame('application/pdf', $response->getHeader('content-type'));
		self::assertSame('16', $response->getHeader('content-length'));
		self::assertStringContainsString('attachment', $response->getHeader('content-disposition'));
		self::assertStringContainsString('report.pdf', $response->getHeader('content-disposition'));
	}

	#[Test]
	public function toApiResponseInlineSetsInlineDisposition(): void
	{
		$response = FileResponse::fromPath($this->testFile)
			->inline()
			->toApiResponse();

		self::assertStringContainsString('inline', $response->getHeader('content-disposition'));
	}

	#[Test]
	public function mimeTypeDetectionForCommonTypes(): void
	{
		$types = [
			'file.pdf' => 'application/pdf',
			'file.png' => 'image/png',
			'file.jpg' => 'image/jpeg',
			'file.jpeg' => 'image/jpeg',
			'file.gif' => 'image/gif',
			'file.zip' => 'application/zip',
			'file.doc' => 'application/msword',
			'file.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'file.xls' => 'application/vnd.ms-excel',
			'file.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'file.csv' => 'text/csv',
			'file.txt' => 'text/plain',
			'file.mp3' => 'audio/mpeg',
			'file.mp4' => 'video/mp4',
			'file.unknown' => 'application/octet-stream',
		];

		foreach ($types as $filename => $expectedType) {
			$response = FileResponse::fromContent('data', $filename);
			self::assertSame($expectedType, $response->getContentType(), "Failed for $filename");
		}
	}

	#[Test]
	public function immutability(): void
	{
		$original = FileResponse::fromPath($this->testFile);
		$modified = $original->withFilename('new.pdf');

		self::assertSame('test-file-response.pdf', $original->getFilename());
		self::assertSame('new.pdf', $modified->getFilename());
		self::assertNotSame($original, $modified);
	}

	// === Path Traversal Protection Tests ===

	#[Test]
	public function setAllowedDirectoriesConfiguresRestriction(): void
	{
		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([sys_get_temp_dir()]);

		self::assertContains(realpath(sys_get_temp_dir()), FileResponse::getAllowedDirectories());

		FileResponse::resetAllowedDirectories();
	}

	#[Test]
	public function allowsFileWithinAllowedDirectory(): void
	{
		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([sys_get_temp_dir()], enforce: true);

		try {
			$response = FileResponse::fromPath($this->testFile);
			self::assertSame('test-file-response.pdf', $response->getFilename());
		} finally {
			FileResponse::resetAllowedDirectories();
		}
	}

	#[Test]
	public function rejectsFileOutsideAllowedDirectory(): void
	{
		// Create a separate allowed directory that doesn't contain testFile
		$allowedDir = sys_get_temp_dir() . '/allowed-only-' . uniqid();
		@mkdir($allowedDir, 0777, true);

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([$allowedDir], enforce: true);

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Access denied');

		try {
			// testFile is in sys_get_temp_dir() but NOT in $allowedDir
			FileResponse::fromPath($this->testFile);
		} finally {
			FileResponse::resetAllowedDirectories();
			@rmdir($allowedDir);
		}
	}

	#[Test]
	public function validatePathCanonicalizesPath(): void
	{
		// Create a symlink scenario or path with ..
		$tempDir = sys_get_temp_dir();
		$testDir = $tempDir . '/file-response-test';
		$subDir = $testDir . '/subdir';

		@mkdir($testDir, 0777, true);
		@mkdir($subDir, 0777, true);

		$testFile = $subDir . '/test.txt';
		file_put_contents($testFile, 'content');

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([$subDir], enforce: true);

		try {
			// Path with .. that resolves to allowed directory
			$pathWithDots = $subDir . '/../subdir/test.txt';
			$response = FileResponse::fromPath($pathWithDots);
			self::assertSame('test.txt', $response->getFilename());
		} finally {
			FileResponse::resetAllowedDirectories();
			@unlink($testFile);
			@rmdir($subDir);
			@rmdir($testDir);
		}
	}

	#[Test]
	public function rejectsPathTraversalAttack(): void
	{
		$allowedDir = sys_get_temp_dir() . '/allowed-test-' . uniqid();
		@mkdir($allowedDir, 0777, true);

		// Create a file outside allowed dir
		$outsideFile = sys_get_temp_dir() . '/outside-test-' . uniqid() . '.txt';
		file_put_contents($outsideFile, 'secret');

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([$allowedDir], enforce: true);

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Access denied');

		try {
			// Try path traversal attack
			FileResponse::fromPath($allowedDir . '/../' . basename($outsideFile));
		} finally {
			FileResponse::resetAllowedDirectories();
			@unlink($outsideFile);
			@rmdir($allowedDir);
		}
	}

	#[Test]
	public function validatePathReturnsCanonicalPath(): void
	{
		FileResponse::resetAllowedDirectories();

		$canonical = FileResponse::validatePath($this->testFile);

		// Should return realpath
		self::assertSame(realpath($this->testFile), $canonical);
	}

	#[Test]
	public function validatePathThrowsForNonexistentFile(): void
	{
		FileResponse::resetAllowedDirectories();

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('File not found or path invalid');

		FileResponse::validatePath('/nonexistent/path/file.txt');
	}

	#[Test]
	public function resetAllowedDirectoriesClearsConfig(): void
	{
		FileResponse::setAllowedDirectories(['/some/path'], enforce: true);
		FileResponse::resetAllowedDirectories();

		self::assertSame([], FileResponse::getAllowedDirectories());
	}

	// === Symlink Protection Tests ===

	#[Test]
	public function rejectsSymlinksByDefault(): void
	{
		// Create a symlink to the test file
		$symlinkPath = sys_get_temp_dir() . '/symlink-test-' . uniqid();

		if (!@symlink($this->testFile, $symlinkPath)) {
			self::markTestSkipped('Cannot create symlinks on this system');
		}

		FileResponse::resetAllowedDirectories();

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Symlinks are not allowed');

		try {
			FileResponse::fromPath($symlinkPath);
		} finally {
			@unlink($symlinkPath);
		}
	}

	#[Test]
	public function allowsSymlinksWhenExplicitlyEnabled(): void
	{
		// Create a symlink to the test file
		$symlinkPath = sys_get_temp_dir() . '/symlink-allowed-' . uniqid();

		if (!@symlink($this->testFile, $symlinkPath)) {
			self::markTestSkipped('Cannot create symlinks on this system');
		}

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([sys_get_temp_dir()], enforce: true, allowSymlinks: true);

		try {
			$response = FileResponse::fromPath($symlinkPath);
			self::assertSame('test-file-response.pdf', $response->getFilename());
		} finally {
			FileResponse::resetAllowedDirectories();
			@unlink($symlinkPath);
		}
	}

	#[Test]
	public function rejectsSymlinkPointingOutsideAllowedDirectory(): void
	{
		// Create allowed directory
		$allowedDir = sys_get_temp_dir() . '/allowed-symlink-test-' . uniqid();
		@mkdir($allowedDir, 0777, true);

		// Create a file outside allowed dir
		$outsideFile = sys_get_temp_dir() . '/outside-symlink-' . uniqid() . '.txt';
		file_put_contents($outsideFile, 'secret content');

		// Create symlink inside allowed dir pointing to file outside
		$symlinkPath = $allowedDir . '/malicious-link';

		if (!@symlink($outsideFile, $symlinkPath)) {
			@unlink($outsideFile);
			@rmdir($allowedDir);
			self::markTestSkipped('Cannot create symlinks on this system');
		}

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([$allowedDir], enforce: true, allowSymlinks: true);

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Access denied');

		try {
			// Symlink is in allowed dir, but points outside - should be rejected
			FileResponse::fromPath($symlinkPath);
		} finally {
			FileResponse::resetAllowedDirectories();
			@unlink($symlinkPath);
			@unlink($outsideFile);
			@rmdir($allowedDir);
		}
	}

	#[Test]
	public function allowsSymlinkPointingInsideAllowedDirectory(): void
	{
		// Create allowed directory with a file and symlink
		$allowedDir = sys_get_temp_dir() . '/allowed-internal-symlink-' . uniqid();
		@mkdir($allowedDir, 0777, true);

		$realFile = $allowedDir . '/real-file.txt';
		file_put_contents($realFile, 'content');

		$symlinkPath = $allowedDir . '/link-to-file';

		if (!@symlink($realFile, $symlinkPath)) {
			@unlink($realFile);
			@rmdir($allowedDir);
			self::markTestSkipped('Cannot create symlinks on this system');
		}

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([$allowedDir], enforce: true, allowSymlinks: true);

		try {
			$response = FileResponse::fromPath($symlinkPath);
			self::assertSame('real-file.txt', $response->getFilename());
		} finally {
			FileResponse::resetAllowedDirectories();
			@unlink($symlinkPath);
			@unlink($realFile);
			@rmdir($allowedDir);
		}
	}

	#[Test]
	public function areSymlinksAllowedReturnsFalseByDefault(): void
	{
		FileResponse::resetAllowedDirectories();

		self::assertFalse(FileResponse::areSymlinksAllowed());
	}

	#[Test]
	public function areSymlinksAllowedReturnsTrueWhenEnabled(): void
	{
		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories(['/some/path'], enforce: true, allowSymlinks: true);

		self::assertTrue(FileResponse::areSymlinksAllowed());

		FileResponse::resetAllowedDirectories();
	}

	#[Test]
	public function resetClearsSymlinkSetting(): void
	{
		FileResponse::setAllowedDirectories(['/some/path'], enforce: true, allowSymlinks: true);
		self::assertTrue(FileResponse::areSymlinksAllowed());

		FileResponse::resetAllowedDirectories();
		self::assertFalse(FileResponse::areSymlinksAllowed());
	}

	#[Test]
	public function handlesChainedSymlinks(): void
	{
		// Create chain: link1 -> link2 -> realFile
		$allowedDir = sys_get_temp_dir() . '/chained-symlink-test-' . uniqid();
		@mkdir($allowedDir, 0777, true);

		$realFile = $allowedDir . '/real-file.txt';
		file_put_contents($realFile, 'content');

		$link2 = $allowedDir . '/link2';
		$link1 = $allowedDir . '/link1';

		if (!@symlink($realFile, $link2) || !@symlink($link2, $link1)) {
			@unlink($link2);
			@unlink($realFile);
			@rmdir($allowedDir);
			self::markTestSkipped('Cannot create symlinks on this system');
		}

		FileResponse::resetAllowedDirectories();
		FileResponse::setAllowedDirectories([$allowedDir], enforce: true, allowSymlinks: true);

		try {
			$response = FileResponse::fromPath($link1);
			self::assertSame('real-file.txt', $response->getFilename());
		} finally {
			FileResponse::resetAllowedDirectories();
			@unlink($link1);
			@unlink($link2);
			@unlink($realFile);
			@rmdir($allowedDir);
		}
	}

	#[Test]
	public function rejectsSymlinkWithoutEnforceWhenSymlinksDisabled(): void
	{
		// Even without enforce, symlinks should be rejected when allowSymlinks=false
		$symlinkPath = sys_get_temp_dir() . '/symlink-no-enforce-' . uniqid();

		if (!@symlink($this->testFile, $symlinkPath)) {
			self::markTestSkipped('Cannot create symlinks on this system');
		}

		FileResponse::resetAllowedDirectories();
		// Note: not calling setAllowedDirectories, so enforce is false by default

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Symlinks are not allowed');

		try {
			FileResponse::fromPath($symlinkPath);
		} finally {
			@unlink($symlinkPath);
		}
	}

	// === Content-Disposition Security Tests ===

	#[Test]
	public function escapesQuotesInFilename(): void
	{
		// Filename with quotes should be escaped to prevent header injection
		$response = FileResponse::fromContent('data', 'test"file.pdf')
			->toApiResponse();

		$disposition = $response->getHeader('content-disposition');

		// Should escape quotes as \"
		self::assertStringContainsString('filename="test\"file.pdf"', $disposition);
		// Should NOT contain unescaped quote that would break the header
		self::assertStringNotContainsString('filename="test"file.pdf"', $disposition);
	}

	#[Test]
	public function escapesBackslashInFilename(): void
	{
		// Filename with backslash should be escaped
		$response = FileResponse::fromContent('data', 'test\\file.pdf')
			->toApiResponse();

		$disposition = $response->getHeader('content-disposition');

		// Should escape backslash as \\
		self::assertStringContainsString('filename="test\\\\file.pdf"', $disposition);
	}

	#[Test]
	public function escapesMultipleSpecialCharsInFilename(): void
	{
		// Filename with multiple special chars
		$response = FileResponse::fromContent('data', 'test"with\\special"chars.pdf')
			->toApiResponse();

		$disposition = $response->getHeader('content-disposition');

		// All quotes and backslashes should be escaped
		self::assertStringContainsString('filename="test\"with\\\\special\"chars.pdf"', $disposition);
	}

	#[Test]
	public function normalFilenameUnchanged(): void
	{
		// Normal filename without special chars should work as before
		$response = FileResponse::fromContent('data', 'normal-file.pdf')
			->toApiResponse();

		$disposition = $response->getHeader('content-disposition');

		self::assertStringContainsString('filename="normal-file.pdf"', $disposition);
	}

	// === X-Content-Type-Options Tests ===

	#[Test]
	public function includesNosniffHeader(): void
	{
		$response = FileResponse::fromContent('data', 'file.pdf')
			->toApiResponse();

		self::assertSame('nosniff', $response->getHeader('x-content-type-options'));
	}

	#[Test]
	public function includesNosniffHeaderForInlineFiles(): void
	{
		$response = FileResponse::fromContent('data', 'image.png')
			->inline()
			->toApiResponse();

		self::assertSame('nosniff', $response->getHeader('x-content-type-options'));
	}

}
