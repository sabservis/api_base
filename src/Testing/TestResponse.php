<?php declare(strict_types = 1);

namespace Sabservis\Api\Testing;

use PHPUnit\Framework\Assert;
use Sabservis\Api\Http\ApiResponse;
use function is_array;
use function json_decode;
use function sprintf;

/**
 * Wrapper around ApiResponse for testing with assertion helpers.
 */
final class TestResponse
{

	private string $body;

	public function __construct(
		private ApiResponse $response,
		string $capturedOutput,
	)
	{
		$this->body = $capturedOutput;
	}

	public function getStatusCode(): int
	{
		return $this->response->getStatusCode();
	}

	public function getHeader(string $name): string|null
	{
		return $this->response->getHeader($name);
	}

	/**
	 * Get all headers with their values.
	 *
	 * @return array<string, list<string>>
	 */
	public function getHeaders(): array
	{
		return $this->response->getHeaders();
	}

	/**
	 * Get all values for a header.
	 *
	 * @return list<string>
	 */
	public function getHeaderValues(string $name): array
	{
		return $this->response->getHeaderValues($name);
	}

	/**
	 * Get header value as comma-separated string.
	 */
	public function getHeaderLine(string $name): string
	{
		return $this->response->getHeaderLine($name);
	}

	public function getBody(): string
	{
		return $this->body;
	}

	/**
	 * @return array<mixed>|null
	 */
	public function json(): array|null
	{
		$decoded = json_decode($this->body, true);

		return is_array($decoded) ? $decoded : null;
	}

	public function getResponse(): ApiResponse
	{
		return $this->response;
	}

	// === Assertions ===

	public function assertStatus(int $expected): self
	{
		Assert::assertSame($expected, $this->getStatusCode(), sprintf(
			'Expected status %d but got %d. Body: %s',
			$expected,
			$this->getStatusCode(),
			$this->body,
		));

		return $this;
	}

	public function assertOk(): self
	{
		return $this->assertStatus(200);
	}

	public function assertCreated(): self
	{
		return $this->assertStatus(201);
	}

	public function assertNoContent(): self
	{
		return $this->assertStatus(204);
	}

	public function assertNotFound(): self
	{
		return $this->assertStatus(404);
	}

	public function assertUnauthorized(): self
	{
		return $this->assertStatus(401);
	}

	public function assertForbidden(): self
	{
		return $this->assertStatus(403);
	}

	public function assertUnprocessable(): self
	{
		return $this->assertStatus(422);
	}

	/**
	 * @param array<mixed> $expected
	 */
	public function assertJson(array $expected): self
	{
		Assert::assertSame($expected, $this->json(), 'JSON body does not match expected.');

		return $this;
	}

	/**
	 * @param array<string, mixed> $expected
	 */
	public function assertJsonContains(array $expected): self
	{
		$json = $this->json();
		Assert::assertIsArray($json);
		foreach ($expected as $key => $value) {
			Assert::assertArrayHasKey($key, $json, sprintf('JSON missing key "%s"', $key));
			Assert::assertSame($value, $json[$key], sprintf('JSON key "%s" does not match', $key));
		}

		return $this;
	}

	public function assertJsonHasKey(string $key): self
	{
		$json = $this->json();
		Assert::assertIsArray($json);
		Assert::assertArrayHasKey($key, $json);

		return $this;
	}

	public function assertHeader(string $name, string $expected): self
	{
		Assert::assertSame($expected, $this->getHeader($name), sprintf(
			'Header "%s" does not match expected value.',
			$name,
		));

		return $this;
	}

	public function assertHeaderExists(string $name): self
	{
		Assert::assertNotNull($this->getHeader($name), sprintf(
			'Header "%s" not found.',
			$name,
		));

		return $this;
	}

}
