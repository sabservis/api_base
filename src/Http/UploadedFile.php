<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use finfo;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use function basename;
use function fclose;
use function file_exists;
use function file_get_contents;
use function fopen;
use function implode;
use function in_array;
use function is_dir;
use function is_uploaded_file;
use function mb_strlen;
use function mkdir;
use function move_uploaded_file;
use function pathinfo;
use function preg_replace;
use function rename;
use function rtrim;
use function str_replace;
use function stream_copy_to_stream;
use function strtolower;
use function substr;
use function trim;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

/**
 * Represents an uploaded file.
 */
final class UploadedFile
{

	private bool $moved = false;

	public function __construct(
		private string $name,
		private string $type,
		private int $size,
		private string $tmpName,
		private int $error,
	)
	{
	}

	/**
	 * Create from $_FILES array item.
	 *
	 * @param array{name: string, type: string, size: int, tmp_name: string, error: int} $file
	 */
	public static function fromArray(array $file): self
	{
		return new self(
			name: $file['name'],
			type: $file['type'],
			size: $file['size'],
			tmpName: $file['tmp_name'],
			error: $file['error'],
		);
	}

	/**
	 * Original filename from client.
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * File extension (lowercase, without dot).
	 *
	 * WARNING: This returns the raw extension from client. Use getSanitizedExtension()
	 * for safe filesystem operations.
	 */
	public function getExtension(): string
	{
		return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
	}

	/**
	 * Sanitized filename safe for filesystem operations.
	 *
	 * Removes:
	 * - Path traversal sequences (../, ..\, /, \)
	 * - Null bytes and control characters
	 * - Leading/trailing dots and spaces
	 * - Potentially dangerous characters
	 *
	 * Limits filename length to prevent filesystem issues.
	 *
	 * @param int $maxLength Maximum filename length (default 255, common filesystem limit)
	 * @return string Safe filename, or 'unnamed' if sanitization results in empty string
	 */
	public function getSanitizedName(int $maxLength = 255): string
	{
		return self::sanitizeFilename($this->name, $maxLength);
	}

	/**
	 * Sanitized file extension (lowercase, without dot).
	 *
	 * Safe for use in filesystem operations and MIME type lookups.
	 *
	 * @return string Lowercase extension without dangerous characters
	 */
	public function getSanitizedExtension(): string
	{
		$extension = pathinfo($this->getSanitizedName(), PATHINFO_EXTENSION);

		return strtolower($extension);
	}

	/**
	 * Sanitize a filename for safe filesystem operations.
	 *
	 * @param string $filename Raw filename to sanitize
	 * @param int $maxLength Maximum length (default 255)
	 * @return string Safe filename
	 */
	public static function sanitizeFilename(string $filename, int $maxLength = 255): string
	{
		// Remove null bytes (critical security issue)
		$filename = str_replace("\0", '', $filename);

		// Get just the basename (removes any path components)
		$filename = basename($filename);

		// Remove path traversal sequences that might survive basename()
		$filename = str_replace(['../', '..\\', '..'], '', $filename);

		// Remove control characters (0x00-0x1F, 0x7F)
		$filename = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $filename);

		// Replace dangerous filesystem characters with underscore
		// Windows reserved: < > : " / \ | ? *
		// Also remove characters that could cause issues in shells/URLs
		$filename = (string) preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', '_', $filename);

		// Replace spaces with underscores (problematic in shells and URLs)
		$filename = str_replace(' ', '_', $filename);

		// Remove leading/trailing dots and underscores (Windows issue, hidden files)
		$filename = trim($filename, "._\t\n\r\0\x0B");

		// Collapse multiple underscores/dots
		$filename = (string) preg_replace('/_{2,}/', '_', $filename);
		$filename = (string) preg_replace('/\.{2,}/', '.', $filename);

		// Remove underscores before dots (cleanup for "name_.ext" -> "name.ext")
		$filename = (string) preg_replace('/_+\./', '.', $filename);

		// Handle empty result
		if ($filename === '' || $filename === '_') {
			return 'unnamed';
		}

		// Limit length while preserving extension
		if (mb_strlen($filename) > $maxLength) {
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
			$name = pathinfo($filename, PATHINFO_FILENAME);

			if ($extension !== '') {
				$extLength = mb_strlen($extension) + 1; // +1 for dot
				$nameLength = $maxLength - $extLength;

				$filename = $nameLength > 0
					? substr($name, 0, $nameLength) . '.' . $extension
					: substr($filename, 0, $maxLength);
			} else {
				$filename = substr($filename, 0, $maxLength);
			}
		}

		return $filename;
	}

	/**
	 * MIME type as sent by client (not reliable, use for hints only).
	 *
	 * WARNING: This value is controlled by the client and can be spoofed.
	 * Use getValidatedContentType() for security-critical decisions.
	 */
	public function getContentType(): string
	{
		return $this->type;
	}

	/**
	 * Server-side validated MIME type using file content analysis (magic bytes).
	 *
	 * This method uses PHP's finfo extension to detect the actual MIME type
	 * based on file content, not the client-provided Content-Type header.
	 *
	 * @return string Detected MIME type, or 'application/octet-stream' if detection fails
	 * @throws InvalidStateException When file was moved or upload failed
	 */
	public function getValidatedContentType(): string
	{
		$this->assertValid();

		if (!file_exists($this->tmpName)) {
			return 'application/octet-stream';
		}

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($this->tmpName);

		if ($mimeType === false) {
			return 'application/octet-stream';
		}

		return $mimeType;
	}

	/**
	 * Check if file's actual MIME type matches one of the allowed types.
	 *
	 * Uses server-side content analysis, not the client-provided Content-Type.
	 *
	 * @param array<string> $allowedTypes List of allowed MIME types (e.g., ['image/jpeg', 'image/png'])
	 * @return bool True if file type is in allowed list
	 * @throws InvalidStateException When file was moved or upload failed
	 */
	public function isAllowedType(array $allowedTypes): bool
	{
		if ($allowedTypes === []) {
			return true; // No restrictions
		}

		$actualType = $this->getValidatedContentType();

		return in_array($actualType, $allowedTypes, true);
	}

	/**
	 * Validate that file's actual MIME type matches one of the allowed types.
	 *
	 * @param array<string> $allowedTypes List of allowed MIME types
	 * @throws InvalidStateException When file type is not allowed, was moved, or upload failed
	 */
	public function assertAllowedType(array $allowedTypes): void
	{
		if ($allowedTypes === []) {
			return; // No restrictions
		}

		$actualType = $this->getValidatedContentType();

		if (!in_array($actualType, $allowedTypes, true)) {
			throw new InvalidStateException(
				"File type '$actualType' is not allowed. Allowed types: " . implode(', ', $allowedTypes),
			);
		}
	}

	/**
	 * File size in bytes.
	 */
	public function getSize(): int
	{
		return $this->size;
	}

	/**
	 * Temporary file path.
	 */
	public function getTempPath(): string
	{
		return $this->tmpName;
	}

	/**
	 * Upload error code (UPLOAD_ERR_* constant).
	 */
	public function getError(): int
	{
		return $this->error;
	}

	/**
	 * Human-readable error message.
	 */
	public function getErrorMessage(): string
	{
		return match ($this->error) {
			UPLOAD_ERR_OK => 'No error',
			UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
			UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
			UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
			UPLOAD_ERR_NO_FILE => 'No file was uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
			UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
			default => 'Unknown error',
		};
	}

	/**
	 * Was file uploaded successfully?
	 */
	public function isOk(): bool
	{
		return $this->error === UPLOAD_ERR_OK;
	}

	/**
	 * Get file contents as string.
	 *
	 * @throws InvalidStateException When file was moved or upload failed
	 */
	public function getContents(): string
	{
		$this->assertValid();

		$contents = file_get_contents($this->tmpName);

		if ($contents === false) {
			throw new InvalidStateException('Failed to read uploaded file contents');
		}

		return $contents;
	}

	/**
	 * Get file as stream resource.
	 *
	 * @return resource
	 * @throws InvalidStateException When file was moved or upload failed
	 */
	public function getStream()
	{
		$this->assertValid();

		$stream = fopen($this->tmpName, 'rb');

		if ($stream === false) {
			throw new InvalidStateException('Failed to open uploaded file stream');
		}

		return $stream;
	}

	/**
	 * Move uploaded file to target path.
	 *
	 * @param string $targetPath Full path where file should be moved
	 * @param bool $overwrite Whether to overwrite existing file (default: false for safety)
	 * @throws InvalidStateException When file was moved, upload failed, or target exists and overwrite is false
	 */
	public function moveTo(string $targetPath, bool $overwrite = false): void
	{
		$this->assertValid();

		if (!$overwrite && file_exists($targetPath)) {
			throw new InvalidStateException("Target file already exists: {$targetPath}");
		}

		// For testing or non-standard uploads, use rename instead of move_uploaded_file
		$success = is_uploaded_file($this->tmpName)
			? move_uploaded_file($this->tmpName, $targetPath)
			: rename($this->tmpName, $targetPath);

		if (!$success) {
			throw new InvalidStateException("Failed to move uploaded file to: {$targetPath}");
		}

		$this->moved = true;
	}

	/**
	 * Move uploaded file to a directory with automatic handling.
	 *
	 * Features:
	 * - Creates target directory if it doesn't exist
	 * - Uses sanitized original filename if no custom filename provided
	 * - Sanitizes custom filename for safety
	 * - Returns the final path for reference
	 *
	 * @param string $directory Target directory path
	 * @param string|null $filename Custom filename (sanitized automatically), or null to use original
	 * @param bool $overwrite Whether to overwrite existing file (default: false for safety)
	 * @return string Final path where file was moved
	 * @throws InvalidStateException When file was moved, upload failed, or target exists and overwrite is false
	 */
	public function moveToDirectory(string $directory, string|null $filename = null, bool $overwrite = false): string
	{
		$this->assertValid();

		// Create directory if it doesn't exist
		if (!is_dir($directory)) {
			if (!mkdir($directory, 0755, true)) {
				throw new InvalidStateException("Failed to create directory: {$directory}");
			}
		}

		// Determine filename - use sanitized original or sanitized custom
		$finalFilename = $filename !== null
			? self::sanitizeFilename($filename)
			: $this->getSanitizedName();

		$targetPath = rtrim($directory, '/\\') . '/' . $finalFilename;

		$this->moveTo($targetPath, $overwrite);

		return $targetPath;
	}

	/**
	 * Copy file contents to a stream.
	 *
	 * @param resource $targetStream
	 * @throws InvalidStateException When file was moved or upload failed
	 */
	public function copyTo($targetStream): void
	{
		$this->assertValid();

		$source = $this->getStream();
		stream_copy_to_stream($source, $targetStream);
		fclose($source);
	}

	private function assertValid(): void
	{
		if (!$this->isOk()) {
			throw new InvalidStateException("Upload error: {$this->getErrorMessage()}");
		}

		if ($this->moved) {
			throw new InvalidStateException('Uploaded file has already been moved');
		}
	}

}
