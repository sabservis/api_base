<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\UploadedFile;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final class UploadedFileTest extends TestCase
{

	#[Test]
	public function createFromArray(): void
	{
		$file = UploadedFile::fromArray([
			'name' => 'test.pdf',
			'type' => 'application/pdf',
			'size' => 12345,
			'tmp_name' => '/tmp/php12345',
			'error' => UPLOAD_ERR_OK,
		]);

		self::assertSame('test.pdf', $file->getName());
		self::assertSame('application/pdf', $file->getContentType());
		self::assertSame(12345, $file->getSize());
		self::assertSame('/tmp/php12345', $file->getTempPath());
		self::assertSame(UPLOAD_ERR_OK, $file->getError());
		self::assertTrue($file->isOk());
	}

	#[Test]
	public function getExtension(): void
	{
		$file = new UploadedFile('document.PDF', 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('pdf', $file->getExtension());
	}

	#[Test]
	public function getExtensionNoExtension(): void
	{
		$file = new UploadedFile('README', 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('', $file->getExtension());
	}

	#[Test]
	public function isOkReturnsFalseForError(): void
	{
		$file = new UploadedFile('test.txt', 'text/plain', 0, '', UPLOAD_ERR_INI_SIZE);

		self::assertFalse($file->isOk());
	}

	#[Test]
	public function errorMessages(): void
	{
		$file = new UploadedFile('', '', 0, '', UPLOAD_ERR_INI_SIZE);
		self::assertSame('File exceeds upload_max_filesize directive', $file->getErrorMessage());

		$file = new UploadedFile('', '', 0, '', UPLOAD_ERR_NO_FILE);
		self::assertSame('No file was uploaded', $file->getErrorMessage());

		$file = new UploadedFile('', '', 0, '', UPLOAD_ERR_OK);
		self::assertSame('No error', $file->getErrorMessage());
	}

	#[Test]
	public function getContentsThrowsOnError(): void
	{
		$file = new UploadedFile('test.txt', 'text/plain', 0, '', UPLOAD_ERR_INI_SIZE);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('Upload error');

		$file->getContents();
	}

	#[Test]
	public function getStreamThrowsOnError(): void
	{
		$file = new UploadedFile('test.txt', 'text/plain', 0, '', UPLOAD_ERR_NO_FILE);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('Upload error');

		$file->getStream();
	}

	#[Test]
	public function moveToThrowsOnError(): void
	{
		$file = new UploadedFile('test.txt', 'text/plain', 0, '', UPLOAD_ERR_INI_SIZE);

		$this->expectException(InvalidStateException::class);

		$file->moveTo('/tmp/target');
	}

	#[Test]
	public function getContentsWithRealFile(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Hello World');

		$file = new UploadedFile('test.txt', 'text/plain', 11, $tmpFile, UPLOAD_ERR_OK);

		self::assertSame('Hello World', $file->getContents());

		unlink($tmpFile);
	}

	#[Test]
	public function moveToWithRealFile(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Test content');

		$targetFile = sys_get_temp_dir() . '/uploaded_' . uniqid();

		$file = new UploadedFile('test.txt', 'text/plain', 12, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile);

		self::assertFileExists($targetFile);
		self::assertSame('Test content', file_get_contents($targetFile));
		self::assertFileDoesNotExist($tmpFile);

		unlink($targetFile);
	}

	#[Test]
	public function moveToThrowsWhenAlreadyMoved(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Test');

		$targetFile = sys_get_temp_dir() . '/uploaded_' . uniqid();

		$file = new UploadedFile('test.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already been moved');

		$file->moveTo($targetFile . '_second');

		// Cleanup
		@unlink($targetFile);
	}

	#[Test]
	public function getStreamWithRealFile(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Stream test');

		$file = new UploadedFile('test.txt', 'text/plain', 11, $tmpFile, UPLOAD_ERR_OK);

		$stream = $file->getStream();
		self::assertIsResource($stream);

		$content = stream_get_contents($stream);
		fclose($stream);

		self::assertSame('Stream test', $content);

		unlink($tmpFile);
	}

	#[Test]
	public function copyToWithRealFile(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Copy test content');

		$targetFile = sys_get_temp_dir() . '/copy_target_' . uniqid();
		$targetStream = fopen($targetFile, 'wb');
		self::assertNotFalse($targetStream);

		$file = new UploadedFile('test.txt', 'text/plain', 17, $tmpFile, UPLOAD_ERR_OK);
		$file->copyTo($targetStream);

		fclose($targetStream);

		self::assertSame('Copy test content', file_get_contents($targetFile));

		unlink($tmpFile);
		unlink($targetFile);
	}

	#[Test]
	public function getContentsThrowsAfterMove(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Test');

		$targetFile = sys_get_temp_dir() . '/moved_' . uniqid();

		$file = new UploadedFile('test.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already been moved');

		$file->getContents();

		@unlink($targetFile);
	}

	#[Test]
	public function getStreamThrowsAfterMove(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Test');

		$targetFile = sys_get_temp_dir() . '/moved_' . uniqid();

		$file = new UploadedFile('test.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already been moved');

		$file->getStream();

		@unlink($targetFile);
	}

	#[Test]
	public function copyToThrowsAfterMove(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Test');

		$targetFile = sys_get_temp_dir() . '/moved_' . uniqid();
		$targetStream = fopen('php://memory', 'wb');
		self::assertNotFalse($targetStream);

		$file = new UploadedFile('test.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already been moved');

		$file->copyTo($targetStream);

		fclose($targetStream);
		@unlink($targetFile);
	}

	#[Test]
	public function moveToThrowsForInvalidTargetPath(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Test');

		$file = new UploadedFile('test.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('Failed to move');

		$file->moveTo('/nonexistent/directory/file.txt');

		@unlink($tmpFile);
	}

	#[Test]
	public function getExtensionWithMultipleDots(): void
	{
		$file = new UploadedFile('backup.2024.tar.gz', 'application/gzip', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('gz', $file->getExtension());
	}

	#[Test]
	public function getExtensionWithTrailingDot(): void
	{
		$file = new UploadedFile('file.', 'application/octet-stream', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('', $file->getExtension());
	}

	#[Test]
	public function allErrorMessages(): void
	{
		$errorCodes = [
			UPLOAD_ERR_OK => 'No error',
			UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
			UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
			UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
			UPLOAD_ERR_NO_FILE => 'No file was uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
			UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
			999 => 'Unknown error',
		];

		foreach ($errorCodes as $code => $expectedMessage) {
			$file = new UploadedFile('', '', 0, '', $code);
			self::assertSame($expectedMessage, $file->getErrorMessage(), "Error code {$code}");
		}
	}

	// === Filename Sanitization Tests ===

	#[Test]
	public function getSanitizedNameReturnsCleanFilename(): void
	{
		$file = new UploadedFile('report.pdf', 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('report.pdf', $file->getSanitizedName());
	}

	#[Test]
	public function getSanitizedNameRemovesPathTraversal(): void
	{
		$testCases = [
			'../../../etc/passwd' => 'etc_passwd',
			'..\\..\\windows\\system32' => 'windows_system32',
			'uploads/../secret.txt' => 'uploads_secret.txt',
			'./hidden.txt' => 'hidden.txt',
			'/etc/passwd' => 'passwd',
			'C:\\Windows\\System32\\cmd.exe' => 'cmd.exe',
		];

		foreach ($testCases as $malicious => $expected) {
			$file = new UploadedFile($malicious, 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);
			$sanitized = $file->getSanitizedName();
			self::assertStringNotContainsString('..', $sanitized, "Path traversal not removed from: $malicious");
			self::assertStringNotContainsString('/', $sanitized, "Forward slash not removed from: $malicious");
			self::assertStringNotContainsString('\\', $sanitized, "Backslash not removed from: $malicious");
		}
	}

	#[Test]
	public function getSanitizedNameRemovesNullBytes(): void
	{
		$file = new UploadedFile("test\0.php.jpg", 'image/jpeg', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName();

		self::assertStringNotContainsString("\0", $sanitized);
		self::assertSame('test.php.jpg', $sanitized);
	}

	#[Test]
	public function getSanitizedNameRemovesControlCharacters(): void
	{
		$file = new UploadedFile("test\x01\x02\x03.txt", 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName();

		self::assertSame('test.txt', $sanitized);
	}

	#[Test]
	public function getSanitizedNameRemovesDangerousCharacters(): void
	{
		$testCases = [
			'file<script>.txt' => 'file_script.txt', // underscores before dot are cleaned
			'file>output.txt' => 'file_output.txt',
			'file:colon.txt' => 'file_colon.txt',
			'file"quote.txt' => 'file_quote.txt',
			'file|pipe.txt' => 'file_pipe.txt',
			'file?query.txt' => 'file_query.txt',
			'file*star.txt' => 'file_star.txt',
		];

		foreach ($testCases as $input => $expected) {
			$file = new UploadedFile($input, 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);
			$sanitized = $file->getSanitizedName();
			self::assertSame($expected, $sanitized, "Failed for input: $input");
		}
	}

	#[Test]
	public function getSanitizedNameRemovesLeadingTrailingDotsAndSpaces(): void
	{
		$testCases = [
			'.hidden' => 'hidden',
			'..htaccess' => 'htaccess',
			'file.' => 'file',
			'  spaces  .txt' => 'spaces.txt', // spaces converted to underscores, then trimmed
			'...dangerous...' => 'dangerous',
		];

		foreach ($testCases as $input => $expected) {
			$file = new UploadedFile($input, 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);
			self::assertSame($expected, $file->getSanitizedName(), "Failed for input: '$input'");
		}
	}

	#[Test]
	public function getSanitizedNameReplacesSpacesWithUnderscores(): void
	{
		$file = new UploadedFile('my document file.pdf', 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('my_document_file.pdf', $file->getSanitizedName());
	}

	#[Test]
	public function getSanitizedNameCollapsesMultipleUnderscores(): void
	{
		$file = new UploadedFile('file___name.txt', 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('file_name.txt', $file->getSanitizedName());
	}

	#[Test]
	public function getSanitizedNameReturnsUnnamedForEmptyResult(): void
	{
		$testCases = [
			'' => 'unnamed',
			'...' => 'unnamed',
			'   ' => 'unnamed',
			'_' => 'unnamed',
		];

		foreach ($testCases as $input => $expected) {
			$file = new UploadedFile($input, 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);
			self::assertSame($expected, $file->getSanitizedName(), "Failed for input: '$input'");
		}
	}

	#[Test]
	public function getSanitizedNameLimitsLength(): void
	{
		$longName = str_repeat('a', 300) . '.pdf';
		$file = new UploadedFile($longName, 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName(255);

		self::assertLessThanOrEqual(255, strlen($sanitized));
		self::assertStringEndsWith('.pdf', $sanitized);
	}

	#[Test]
	public function getSanitizedNameLimitsLengthWithCustomMax(): void
	{
		$file = new UploadedFile('very_long_filename.txt', 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName(10);

		self::assertLessThanOrEqual(10, strlen($sanitized));
	}

	#[Test]
	public function getSanitizedNamePreservesExtensionWhenTruncating(): void
	{
		$file = new UploadedFile('very_long_document_name.pdf', 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName(15);

		self::assertLessThanOrEqual(15, strlen($sanitized));
		self::assertStringEndsWith('.pdf', $sanitized);
	}

	#[Test]
	public function getSanitizedExtensionReturnsCleanExtension(): void
	{
		$file = new UploadedFile('document.PDF', 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('pdf', $file->getSanitizedExtension());
	}

	#[Test]
	public function getSanitizedExtensionHandlesPathTraversal(): void
	{
		$file = new UploadedFile('../../../etc/passwd.txt', 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('txt', $file->getSanitizedExtension());
	}

	#[Test]
	public function getSanitizedExtensionReturnsEmptyForNoExtension(): void
	{
		$file = new UploadedFile('README', 'text/plain', 100, '/tmp/x', UPLOAD_ERR_OK);

		self::assertSame('', $file->getSanitizedExtension());
	}

	#[Test]
	public function staticSanitizeFilenameMethod(): void
	{
		self::assertSame('test.pdf', UploadedFile::sanitizeFilename('test.pdf'));
		self::assertSame('passwd', UploadedFile::sanitizeFilename('../../../etc/passwd'));
		self::assertSame('unnamed', UploadedFile::sanitizeFilename(''));
	}

	#[Test]
	public function getSanitizedNameHandlesUnicodeCharacters(): void
	{
		$file = new UploadedFile('документ.pdf', 'application/pdf', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName();

		// Unicode should be preserved (not dangerous)
		self::assertSame('документ.pdf', $sanitized);
	}

	#[Test]
	public function getSanitizedNameHandlesDoubleExtension(): void
	{
		$file = new UploadedFile('malware.php.jpg', 'image/jpeg', 100, '/tmp/x', UPLOAD_ERR_OK);

		$sanitized = $file->getSanitizedName();

		// Double extension should be preserved (it's the developer's job to check actual content)
		self::assertSame('malware.php.jpg', $sanitized);
	}

	// === MIME Type Validation Tests ===

	#[Test]
	public function getValidatedContentTypeDetectsActualMimeType(): void
	{
		// Create a real text file
		$tmpFile = tempnam(sys_get_temp_dir(), 'mime_test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Hello World - plain text content');

		$file = new UploadedFile(
			'fake.pdf', // Fake name
			'application/pdf', // Fake MIME from client
			33,
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		// Should detect actual type, not trust client
		$actualType = $file->getValidatedContentType();
		self::assertSame('text/plain', $actualType);

		unlink($tmpFile);
	}

	#[Test]
	public function getValidatedContentTypeDetectsPngImage(): void
	{
		// Create a minimal valid PNG file (8x8 red pixel)
		$tmpFile = tempnam(sys_get_temp_dir(), 'png_test');
		self::assertNotFalse($tmpFile);

		// Minimal PNG header + IHDR + IDAT + IEND
		$png = "\x89PNG\r\n\x1a\n" . // PNG signature
			"\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde" . // IHDR
			"\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N" . // IDAT
			"\x00\x00\x00\x00IEND\xaeB`\x82"; // IEND
		file_put_contents($tmpFile, $png);

		$file = new UploadedFile(
			'image.jpg', // Wrong extension
			'image/jpeg', // Wrong MIME from client
			strlen($png),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		$actualType = $file->getValidatedContentType();
		self::assertSame('image/png', $actualType);

		unlink($tmpFile);
	}

	#[Test]
	public function getValidatedContentTypeReturnsOctetStreamForUnknown(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'binary_test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, "\x00\x01\x02\x03\x04\x05"); // Random binary

		$file = new UploadedFile('data.bin', 'application/octet-stream', 6, $tmpFile, UPLOAD_ERR_OK);

		$actualType = $file->getValidatedContentType();
		self::assertSame('application/octet-stream', $actualType);

		unlink($tmpFile);
	}

	#[Test]
	public function getValidatedContentTypeThrowsWhenFileMoved(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'moved_test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'test');

		$targetFile = sys_get_temp_dir() . '/moved_' . uniqid();

		$file = new UploadedFile('test.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already been moved');

		$file->getValidatedContentType();

		@unlink($targetFile);
	}

	#[Test]
	public function isAllowedTypeReturnsTrueForAllowedType(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'allowed_test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Plain text content');

		$file = new UploadedFile('doc.txt', 'text/plain', 18, $tmpFile, UPLOAD_ERR_OK);

		self::assertTrue($file->isAllowedType(['text/plain', 'text/html']));

		unlink($tmpFile);
	}

	#[Test]
	public function isAllowedTypeReturnsFalseForDisallowedType(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'disallowed_test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Plain text content');

		$file = new UploadedFile('doc.txt', 'text/plain', 18, $tmpFile, UPLOAD_ERR_OK);

		self::assertFalse($file->isAllowedType(['image/jpeg', 'image/png']));

		unlink($tmpFile);
	}

	#[Test]
	public function isAllowedTypeReturnsTrueForEmptyAllowedList(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'empty_test');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Any content');

		$file = new UploadedFile('file.bin', 'application/octet-stream', 11, $tmpFile, UPLOAD_ERR_OK);

		// Empty list = no restrictions = everything allowed
		self::assertTrue($file->isAllowedType([]));

		unlink($tmpFile);
	}

	#[Test]
	public function assertAllowedTypePassesForValidType(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'assert_pass');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Text content');

		$file = new UploadedFile('doc.txt', 'text/plain', 12, $tmpFile, UPLOAD_ERR_OK);

		// Should not throw
		$file->assertAllowedType(['text/plain']);
		self::assertTrue(true); // If we got here, test passed

		unlink($tmpFile);
	}

	#[Test]
	public function assertAllowedTypeThrowsForInvalidType(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'assert_fail');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Text content');

		$file = new UploadedFile('doc.txt', 'text/plain', 12, $tmpFile, UPLOAD_ERR_OK);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage("File type 'text/plain' is not allowed");

		$file->assertAllowedType(['image/jpeg', 'image/png']);

		@unlink($tmpFile);
	}

	#[Test]
	public function assertAllowedTypePassesForEmptyList(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'assert_empty');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Any content');

		$file = new UploadedFile('file.bin', 'application/octet-stream', 11, $tmpFile, UPLOAD_ERR_OK);

		// Empty list = no restrictions
		$file->assertAllowedType([]);
		self::assertTrue(true);

		unlink($tmpFile);
	}

	#[Test]
	public function mimeValidationDetectsPhpInJpeg(): void
	{
		// Simulate attack: PHP code with fake JPEG header
		$tmpFile = tempnam(sys_get_temp_dir(), 'php_attack');
		self::assertNotFalse($tmpFile);

		// This is NOT a valid JPEG - finfo will detect it as text/x-php or text/plain
		$maliciousContent = "<?php system(\$_GET['cmd']); ?>";
		file_put_contents($tmpFile, $maliciousContent);

		$file = new UploadedFile(
			'innocent.jpg',
			'image/jpeg', // Attacker claims it's JPEG
			strlen($maliciousContent),
			$tmpFile,
			UPLOAD_ERR_OK,
		);

		// Server-side detection should NOT return image/jpeg
		$actualType = $file->getValidatedContentType();
		self::assertNotSame('image/jpeg', $actualType);

		// Should be detected as text or PHP
		self::assertStringContainsString('text', $actualType);

		unlink($tmpFile);
	}

	// =====================
	// moveTo with overwrite tests
	// =====================

	#[Test]
	public function moveToThrowsWhenFileExistsAndOverwriteIsFalse(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		$targetFile = tempnam(sys_get_temp_dir(), 'target');
		self::assertNotFalse($tmpFile);
		self::assertNotFalse($targetFile);

		file_put_contents($tmpFile, 'Source content');
		file_put_contents($targetFile, 'Existing content');

		$file = new UploadedFile('test.txt', 'text/plain', 14, $tmpFile, UPLOAD_ERR_OK);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already exists');

		try {
			$file->moveTo($targetFile, overwrite: false);
		} finally {
			@unlink($tmpFile);
			@unlink($targetFile);
		}
	}

	#[Test]
	public function moveToOverwritesWhenExplicitlyAllowed(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		$targetFile = tempnam(sys_get_temp_dir(), 'target');
		self::assertNotFalse($tmpFile);
		self::assertNotFalse($targetFile);

		file_put_contents($tmpFile, 'New content');
		file_put_contents($targetFile, 'Old content');

		$file = new UploadedFile('test.txt', 'text/plain', 11, $tmpFile, UPLOAD_ERR_OK);
		$file->moveTo($targetFile, overwrite: true);

		self::assertFileExists($targetFile);
		self::assertSame('New content', file_get_contents($targetFile));

		@unlink($targetFile);
	}

	#[Test]
	public function moveToDefaultsToNoOverwrite(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		$targetFile = tempnam(sys_get_temp_dir(), 'target');
		self::assertNotFalse($tmpFile);
		self::assertNotFalse($targetFile);

		file_put_contents($tmpFile, 'Source');
		file_put_contents($targetFile, 'Existing');

		$file = new UploadedFile('test.txt', 'text/plain', 6, $tmpFile, UPLOAD_ERR_OK);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already exists');

		try {
			$file->moveTo($targetFile); // No overwrite parameter = false
		} finally {
			@unlink($tmpFile);
			@unlink($targetFile);
		}
	}

	// =====================
	// moveToDirectory tests
	// =====================

	#[Test]
	public function moveToDirectoryUsesOriginalSanitizedName(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Content');

		$targetDir = sys_get_temp_dir() . '/upload_test_' . uniqid();

		$file = new UploadedFile('My Document.pdf', 'application/pdf', 7, $tmpFile, UPLOAD_ERR_OK);
		$finalPath = $file->moveToDirectory($targetDir);

		self::assertFileExists($finalPath);
		// Filename is sanitized (spaces -> underscores)
		self::assertSame($targetDir . '/My_Document.pdf', $finalPath);
		self::assertSame('Content', file_get_contents($finalPath));

		@unlink($finalPath);
		@rmdir($targetDir);
	}

	#[Test]
	public function moveToDirectoryCreatesDirectoryIfNotExists(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Content');

		$targetDir = sys_get_temp_dir() . '/new_directory_' . uniqid();
		self::assertDirectoryDoesNotExist($targetDir);

		$file = new UploadedFile('test.txt', 'text/plain', 7, $tmpFile, UPLOAD_ERR_OK);
		$finalPath = $file->moveToDirectory($targetDir);

		self::assertDirectoryExists($targetDir);
		self::assertFileExists($finalPath);

		@unlink($finalPath);
		@rmdir($targetDir);
	}

	#[Test]
	public function moveToDirectoryUsesCustomFilename(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Data');

		$targetDir = sys_get_temp_dir() . '/upload_custom_' . uniqid();

		$file = new UploadedFile('original.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		$finalPath = $file->moveToDirectory($targetDir, 'custom_name.txt');

		self::assertFileExists($finalPath);
		self::assertSame($targetDir . '/custom_name.txt', $finalPath);

		@unlink($finalPath);
		@rmdir($targetDir);
	}

	#[Test]
	public function moveToDirectorySanitizesCustomFilename(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'Data');

		$targetDir = sys_get_temp_dir() . '/upload_sanitize_' . uniqid();

		$file = new UploadedFile('original.txt', 'text/plain', 4, $tmpFile, UPLOAD_ERR_OK);
		// Path traversal attempt in custom filename
		$finalPath = $file->moveToDirectory($targetDir, '../../../etc/passwd');

		// Should be sanitized to just 'passwd' (basename removes path)
		self::assertStringEndsWith('/passwd', $finalPath);
		self::assertStringStartsWith($targetDir, $finalPath);
		self::assertFileExists($finalPath);

		@unlink($finalPath);
		@rmdir($targetDir);
	}

	#[Test]
	public function moveToDirectoryThrowsWhenFileExistsAndOverwriteIsFalse(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'New');

		$targetDir = sys_get_temp_dir() . '/upload_exists_' . uniqid();
		mkdir($targetDir);
		file_put_contents($targetDir . '/test.txt', 'Existing');

		$file = new UploadedFile('test.txt', 'text/plain', 3, $tmpFile, UPLOAD_ERR_OK);

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('already exists');

		try {
			$file->moveToDirectory($targetDir);
		} finally {
			@unlink($tmpFile);
			@unlink($targetDir . '/test.txt');
			@rmdir($targetDir);
		}
	}

	#[Test]
	public function moveToDirectoryOverwritesWhenAllowed(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'New content');

		$targetDir = sys_get_temp_dir() . '/upload_overwrite_' . uniqid();
		mkdir($targetDir);
		file_put_contents($targetDir . '/test.txt', 'Old content');

		$file = new UploadedFile('test.txt', 'text/plain', 11, $tmpFile, UPLOAD_ERR_OK);
		$finalPath = $file->moveToDirectory($targetDir, overwrite: true);

		self::assertSame('New content', file_get_contents($finalPath));

		@unlink($finalPath);
		@rmdir($targetDir);
	}

	#[Test]
	public function moveToDirectoryReturnsFullPath(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'source');
		self::assertNotFalse($tmpFile);
		file_put_contents($tmpFile, 'X');

		$targetDir = sys_get_temp_dir() . '/upload_path_' . uniqid();

		$file = new UploadedFile('report.pdf', 'application/pdf', 1, $tmpFile, UPLOAD_ERR_OK);
		$finalPath = $file->moveToDirectory($targetDir);

		self::assertSame($targetDir . '/report.pdf', $finalPath);

		@unlink($finalPath);
		@rmdir($targetDir);
	}

}
