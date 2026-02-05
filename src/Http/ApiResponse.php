<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Utils\HeaderSanitizer;
use function array_key_exists;
use function implode;
use function is_resource;
use function json_encode;
use function strtolower;
use const JSON_THROW_ON_ERROR;

class ApiResponse
{

	/** @var array<string, mixed> */
	private array $attributes = [];

	/**
	 * Headers stored as arrays to support multiple values (e.g., Set-Cookie).
	 *
	 * @var array<string, list<string>>
	 */
	private array $headers = [];

	/** @var string|resource */
	private mixed $body = '';

	private string $protocolVersion = '1.1';

	public function __construct(
		private int $statusCode = 200,
		private string $reasonPhrase = '',
	)
	{
		if ($this->reasonPhrase === '') {
			$this->reasonPhrase = self::getDefaultReasonPhrase($statusCode);
		}
	}

	// === Status ===

	/**
	 * Create 200 OK response with object.
	 */
	public static function ok(object $data): self
	{
		return (new self(200))->withObject($data);
	}

	/**
	 * Create 201 Created response with object.
	 */
	public static function created(object $data): self
	{
		return (new self(201))->withObject($data);
	}

	/**
	 * Create 204 No Content response.
	 */
	public static function noContent(): self
	{
		return new self(204);
	}

	/**
	 * Create list response (with or without pagination meta).
	 *
	 * @param array<mixed> $data
	 */
	public static function list(
		array $data,
		int|null $total = null,
		int|null $limit = null,
		int|null $offset = null,
	): self
	{
		$listResponse = $total !== null
			? ListResponse::create($data, $total, $limit ?? 0, $offset ?? 0)
			: ListResponse::withoutMeta($data);

		return (new self(200))->withObject($listResponse);
	}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	public function getReasonPhrase(): string
	{
		return $this->reasonPhrase;
	}

	public function withStatus(int $code, string $reasonPhrase = ''): self
	{
		$new = clone $this;
		$new->statusCode = $code;
		$new->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : self::getDefaultReasonPhrase($code);

		return $new;
	}

	public function getProtocolVersion(): string
	{
		return $this->protocolVersion;
	}

	public function withProtocolVersion(string $version): self
	{
		$new = clone $this;
		$new->protocolVersion = $version;

		return $new;
	}

	// === Headers ===

	public function hasHeader(string $name): bool
	{
		return isset($this->headers[strtolower($name)]);
	}

	/**
	 * Get first header value (for backwards compatibility).
	 *
	 * For headers with multiple values, returns only the first one.
	 * Use getHeaderValues() to get all values.
	 */
	public function getHeader(string $name): string|null
	{
		$values = $this->headers[strtolower($name)] ?? [];

		return $values[0] ?? null;
	}

	/**
	 * Get all values for a header as array.
	 *
	 * @return list<string>
	 */
	public function getHeaderValues(string $name): array
	{
		return $this->headers[strtolower($name)] ?? [];
	}

	/**
	 * Get header value as comma-separated string (PSR-7 compatible).
	 *
	 * Note: For Set-Cookie headers, use getHeaderValues() instead,
	 * as Set-Cookie values cannot be joined with commas.
	 */
	public function getHeaderLine(string $name): string
	{
		$values = $this->headers[strtolower($name)] ?? [];

		return implode(', ', $values);
	}

	/**
	 * Get all headers with their values.
	 *
	 * @return array<string, list<string>>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * Set header value, replacing any existing values.
	 */
	public function withHeader(string $name, string $value): self
	{
		$new = clone $this;
		// Sanitize value to prevent HTTP response splitting attacks
		$new->headers[strtolower($name)] = [HeaderSanitizer::sanitize($value)];

		return $new;
	}

	/**
	 * Add header value (appends to existing values as separate entry).
	 *
	 * Unlike comma-joining, this properly supports headers like Set-Cookie
	 * that require separate header lines for each value.
	 */
	public function withAddedHeader(string $name, string $value): self
	{
		$new = clone $this;
		$key = strtolower($name);
		// Sanitize value to prevent HTTP response splitting attacks
		$sanitizedValue = HeaderSanitizer::sanitize($value);

		$new->headers[$key] = $this->headers[$key] ?? [];
		$new->headers[$key][] = $sanitizedValue;

		return $new;
	}

	/**
	 * Remove a header.
	 */
	public function withoutHeader(string $name): self
	{
		$new = clone $this;
		unset($new->headers[strtolower($name)]);

		return $new;
	}

	// === Body ===

	/**
	 * @return string|resource
	 */
	public function getBody(): mixed
	{
		return $this->body;
	}

	/**
	 * @param string|resource $body
	 */
	public function withBody(mixed $body): self
	{
		$new = clone $this;
		$new->body = $body;

		return $new;
	}

	public function writeBody(string $content): self
	{
		if (is_resource($this->body)) {
			throw new RuntimeStateException('Cannot write to resource body');
		}

		$new = clone $this;
		$new->body .= $content;

		return $new;
	}

	/**
	 * Write JSON encoded data to body and set Content-Type header.
	 *
	 * Sets X-Content-Type-Options: nosniff to prevent MIME sniffing attacks.
	 */
	public function writeJsonBody(mixed $data): self
	{
		return $this
			->withHeader('Content-Type', 'application/json')
			->withHeader('X-Content-Type-Options', 'nosniff')
			->writeBody(json_encode($data, JSON_THROW_ON_ERROR));
	}

	public function isStreamBody(): bool
	{
		return is_resource($this->body);
	}

	// === Attributes ===

	public function hasAttribute(string $name): bool
	{
		return array_key_exists($name, $this->attributes);
	}

	public function getAttribute(string $name, mixed $default = null): mixed
	{
		return $this->attributes[$name] ?? $default;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	public function withAttribute(string $name, mixed $value): self
	{
		$new = clone $this;
		$new->attributes[$name] = $value;

		return $new;
	}// === Static factories ===


	// === API helpers ===

	public function getObject(): object|null
	{
		/** @var object|null */
		return $this->attributes[ResponseAttributes::Object->value] ?? null;
	}

	public function withObject(object $entity): self
	{
		return $this->withAttribute(ResponseAttributes::Object->value, $entity);
	}

	private static function getDefaultReasonPhrase(int $code): string
	{
		return match ($code) {
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			208 => 'Already Reported',
			226 => 'IM Used',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Payload Too Large',
			414 => 'URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Range Not Satisfiable',
			417 => 'Expectation Failed',
			421 => 'Misdirected Request',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
			428 => 'Precondition Required',
			429 => 'Too Many Requests',
			431 => 'Request Header Fields Too Large',
			451 => 'Unavailable For Legal Reasons',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			508 => 'Loop Detected',
			510 => 'Not Extended',
			511 => 'Network Authentication Required',
			default => '',
		};
	}

}
