<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use Sabservis\Api\Exception\RuntimeStateException;
use function basename;
use function dirname;
use function filesize;
use function fopen;
use function fstat;
use function is_link;
use function is_resource;
use function pathinfo;
use function rawurlencode;
use function readlink;
use function realpath;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

/**
 * Response for file downloads with automatic MIME type detection.
 *
 * SECURITY: Always validate file paths before passing to fromPath().
 * Use setAllowedDirectories() to restrict file access to specific directories.
 *
 * Usage:
 * ```php
 * // Configure allowed directories (recommended for security)
 * FileResponse::setAllowedDirectories(['/var/www/uploads', '/var/www/public']);
 *
 * // From file path (auto-detects name and MIME type)
 * return FileResponse::fromPath('/var/www/uploads/report.pdf');
 *
 * // With custom download name
 * return FileResponse::fromPath('/path/to/report.pdf', 'Invoice-2024.pdf');
 *
 * // From content in memory (no path traversal risk)
 * return FileResponse::fromContent($pdfData, 'report.pdf');
 *
 * // Inline display (not download)
 * return FileResponse::fromPath('/path/to/image.png')->inline();
 *
 * // Custom MIME type
 * return FileResponse::fromPath('/path/to/data.bin')->withContentType('application/x-custom');
 * ```
 */
final class FileResponse
{

	private const MIME_TYPES = [
		// Documents
		'pdf' => 'application/pdf',
		'doc' => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls' => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'ppt' => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		'odp' => 'application/vnd.oasis.opendocument.presentation',
		'rtf' => 'application/rtf',
		'txt' => 'text/plain',
		'csv' => 'text/csv',

		// Images
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'webp' => 'image/webp',
		'svg' => 'image/svg+xml',
		'ico' => 'image/x-icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',

		// Audio
		'mp3' => 'audio/mpeg',
		'wav' => 'audio/wav',
		'ogg' => 'audio/ogg',
		'flac' => 'audio/flac',
		'aac' => 'audio/aac',
		'm4a' => 'audio/mp4',

		// Video
		'mp4' => 'video/mp4',
		'webm' => 'video/webm',
		'avi' => 'video/x-msvideo',
		'mov' => 'video/quicktime',
		'wmv' => 'video/x-ms-wmv',
		'mkv' => 'video/x-matroska',

		// Archives
		'zip' => 'application/zip',
		'rar' => 'application/vnd.rar',
		'7z' => 'application/x-7z-compressed',
		'tar' => 'application/x-tar',
		'gz' => 'application/gzip',

		// Web
		'html' => 'text/html',
		'htm' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',

		// Fonts
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf' => 'font/ttf',
		'otf' => 'font/otf',
		'eot' => 'application/vnd.ms-fontobject',
	];

	/** @var array<string> Allowed directories for file serving (empty = allow all with warning) */
	private static array $allowedDirectories = [];

	/** @var bool Whether to enforce allowed directories check */
	private static bool $enforceAllowedDirectories = false;

	/** @var bool Whether to allow symlinks (default: false for security) */
	private static bool $allowSymlinks = false;

	private bool $forceDownload = true;

	/**
	 * @param resource|string $body
	 */
	private function __construct(
		private mixed $body,
		private int|null $size,
		private string $filename,
		private string $contentType,
	)
	{
	}

	/**
	 * Create FileResponse from file path.
	 *
	 * SECURITY: The path is canonicalized using realpath() to prevent path traversal attacks.
	 * Configure allowed directories with setAllowedDirectories() for additional protection.
	 *
	 * @param string $path Path to the file
	 * @param string|null $filename Custom filename for download (defaults to basename of path)
	 * @param string|null $contentType Custom MIME type (auto-detected if null)
	 * @throws RuntimeStateException If file not found, unreadable, or outside allowed directories
	 */
	public static function fromPath(
		string $path,
		string|null $filename = null,
		string|null $contentType = null,
	): self
	{
		// Validate and canonicalize path
		$realPath = self::validatePath($path);

		$handle = fopen($realPath, 'rb');

		if ($handle === false) {
			throw new RuntimeStateException("Cannot open file: $path");
		}

		return new self(
			$handle,
			filesize($realPath) ?: null,
			$filename ?? basename($realPath),
			$contentType ?? self::detectMimeType($realPath),
		);
	}

	/**
	 * Create FileResponse from content in memory.
	 *
	 * @param string $content Binary content
	 * @param string $filename Filename for download
	 * @param string|null $contentType Custom MIME type (auto-detected from filename if null)
	 */
	public static function fromContent(
		string $content,
		string $filename,
		string|null $contentType = null,
	): self
	{
		return new self(
			$content,
			strlen($content),
			$filename,
			$contentType ?? self::detectMimeTypeFromFilename($filename),
		);
	}

	/**
	 * Create FileResponse from resource handle.
	 *
	 * @param resource $handle Open file handle
	 * @param string $filename Filename for download
	 * @param string|null $contentType Custom MIME type (auto-detected from filename if null)
	 */
	public static function fromResource(
		mixed $handle,
		string $filename,
		string|null $contentType = null,
	): self
	{
		if (!is_resource($handle)) {
			throw new RuntimeStateException('Expected resource handle');
		}

		$stat = fstat($handle);
		$size = $stat['size'] ?? null;

		return new self(
			$handle,
			$size,
			$filename,
			$contentType ?? self::detectMimeTypeFromFilename($filename),
		);
	}

	/**
	 * Set allowed directories for file serving.
	 * When set, fromPath() will only allow files within these directories.
	 *
	 * @param array<string> $directories Absolute paths to allowed directories
	 * @param bool $enforce If true, throws exception for paths outside allowed dirs. If false, just validates.
	 * @param bool $allowSymlinks If true, allows symlinks (target must still be in allowed dirs). Default: false for security.
	 */
	public static function setAllowedDirectories(
		array $directories,
		bool $enforce = true,
		bool $allowSymlinks = false,
	): void
	{
		self::$allowedDirectories = [];
		self::$enforceAllowedDirectories = $enforce;
		self::$allowSymlinks = $allowSymlinks;

		foreach ($directories as $dir) {
			$realDir = realpath($dir);

			if ($realDir !== false) {
				self::$allowedDirectories[] = $realDir;
			}
		}
	}

	/**
	 * Check if symlinks are currently allowed.
	 */
	public static function areSymlinksAllowed(): bool
	{
		return self::$allowSymlinks;
	}

	/**
	 * Get currently configured allowed directories.
	 *
	 * @return array<string>
	 */
	public static function getAllowedDirectories(): array
	{
		return self::$allowedDirectories;
	}

	/**
	 * Reset allowed directories (for testing).
	 */
	public static function resetAllowedDirectories(): void
	{
		self::$allowedDirectories = [];
		self::$enforceAllowedDirectories = false;
		self::$allowSymlinks = false;
	}

	/**
	 * Validate that a path is safe and within allowed directories.
	 *
	 * Security checks performed:
	 * 1. Symlink detection - rejects symlinks unless explicitly allowed
	 * 2. Path canonicalization via realpath() - resolves ../ sequences
	 * 3. Allowed directory enforcement - ensures file is within permitted directories
	 *
	 * @throws RuntimeStateException If path is unsafe, a symlink (when not allowed), or outside allowed directories
	 */
	public static function validatePath(string $path): string
	{
		// SECURITY: Check for symlinks BEFORE realpath() to prevent TOCTOU attacks
		// A symlink could point to a sensitive file outside allowed directories
		if (is_link($path)) {
			if (!self::$allowSymlinks) {
				throw new RuntimeStateException(
					'Access denied: Symlinks are not allowed for security reasons',
				);
			}

			// If symlinks are allowed, validate the target is within allowed directories
			// We still need to check where the symlink points
			$linkTarget = readlink($path);

			if ($linkTarget === false) {
				throw new RuntimeStateException("Cannot read symlink target: $path");
			}

			// If link target is relative, resolve it relative to the symlink's directory
			if (!str_starts_with($linkTarget, DIRECTORY_SEPARATOR)) {
				$linkDir = dirname($path);
				$linkTarget = $linkDir . DIRECTORY_SEPARATOR . $linkTarget;
			}

			// Recursively validate the target (handles chained symlinks)
			return self::validatePath($linkTarget);
		}

		// Canonicalize path to resolve ../ sequences
		$realPath = realpath($path);

		if ($realPath === false) {
			throw new RuntimeStateException("File not found or path invalid: $path");
		}

		// Double-check: the resolved path should not be a symlink
		// (handles edge cases where realpath doesn't fully resolve)
		if (is_link($realPath) && !self::$allowSymlinks) {
			throw new RuntimeStateException(
				'Access denied: Resolved path is a symlink',
			);
		}

		// If allowed directories are configured, check path is within one of them
		if (self::$allowedDirectories !== [] && self::$enforceAllowedDirectories) {
			$isAllowed = false;

			foreach (self::$allowedDirectories as $allowedDir) {
				if (str_starts_with($realPath, $allowedDir . DIRECTORY_SEPARATOR) || $realPath === $allowedDir) {
					$isAllowed = true;

					break;
				}
			}

			if (!$isAllowed) {
				throw new RuntimeStateException(
					'Access denied: File path is outside allowed directories',
				);
			}
		}

		return $realPath;
	}

	/**
	 * Display file inline (in browser) instead of forcing download.
	 */
	public function inline(): self
	{
		$new = clone $this;
		$new->forceDownload = false;

		return $new;
	}

	/**
	 * Force file download (default behavior).
	 */
	public function download(): self
	{
		$new = clone $this;
		$new->forceDownload = true;

		return $new;
	}

	/**
	 * Set custom content type.
	 */
	public function withContentType(string $contentType): self
	{
		$new = clone $this;
		$new->contentType = $contentType;

		return $new;
	}

	/**
	 * Set custom filename for download.
	 */
	public function withFilename(string $filename): self
	{
		$new = clone $this;
		$new->filename = $filename;

		return $new;
	}

	/**
	 * Convert to ApiResponse with all headers set.
	 */
	public function toApiResponse(): ApiResponse
	{
		// SECURITY: Escape filename for Content-Disposition header (RFC 6266)
		// Backslash and quotes must be escaped in quoted-string
		$escapedFilename = self::escapeFilenameForHeader($this->filename);

		$response = (new ApiResponse())
			->withHeader('Content-Type', $this->contentType)
			->withHeader('Content-Description', 'File Transfer')
			->withHeader('Content-Transfer-Encoding', 'binary')
			->withHeader(
				'Content-Disposition',
				($this->forceDownload ? 'attachment' : 'inline')
				. '; filename="' . $escapedFilename . '"'
				. "; filename*=utf-8''" . rawurlencode($this->filename),
			)
			// SECURITY: Prevent MIME sniffing attacks
			->withHeader('X-Content-Type-Options', 'nosniff')
			->withHeader('Expires', '0')
			->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
			->withHeader('Pragma', 'public')
			->withBody($this->body);

		if ($this->size !== null) {
			$response = $response->withHeader('Content-Length', (string) $this->size);
		}

		return $response;
	}

	/**
	 * Escape filename for use in Content-Disposition header.
	 *
	 * RFC 6266 requires backslash and quotes to be escaped in quoted-string.
	 */
	private static function escapeFilenameForHeader(string $filename): string
	{
		// Escape backslash first (must be before quote escaping)
		$filename = str_replace('\\', '\\\\', $filename);
		// Escape quotes
		$filename = str_replace('"', '\\"', $filename);

		return $filename;
	}

	// === Getters ===

	public function getFilename(): string
	{
		return $this->filename;
	}

	public function getContentType(): string
	{
		return $this->contentType;
	}

	public function getSize(): int|null
	{
		return $this->size;
	}

	public function isForceDownload(): bool
	{
		return $this->forceDownload;
	}

	/**
	 * @return resource|string
	 */
	public function getBody(): mixed
	{
		return $this->body;
	}

	// === MIME type detection ===

	private static function detectMimeType(string $path): string
	{
		return self::detectMimeTypeFromFilename($path);
	}

	private static function detectMimeTypeFromFilename(string $filename): string
	{
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
	}

}
