<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use function array_key_exists;

/**
 * OpenAPI definition - contains OpenAPI documentation metadata.
 */
final class OpenApiDefinition
{

	/** @var array<EndpointResponse> */
	private array $responses = [];

	/** @var array<string, mixed> */
	private array $tags = [];

	/** @var array<string, mixed> */
	private array $openApi = [];

	private string|null $summary = null;

	private bool $deprecated = false;

	/**
	 * @return array<EndpointResponse>
	 */
	public function getResponses(): array
	{
		return $this->responses;
	}

	public function hasResponse(string $code): bool
	{
		return isset($this->responses[$code]);
	}

	public function addResponse(EndpointResponse $response): void
	{
		$this->responses[$response->getCode()] = $response;
	}

	/**
	 * @param array<EndpointResponse> $responses
	 */
	public function setResponses(array $responses): void
	{
		foreach ($responses as $response) {
			$this->addResponse($response);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getTags(): array
	{
		return $this->tags;
	}

	public function getTag(string $name): mixed
	{
		return $this->tags[$name] ?? null;
	}

	public function hasTag(string $name): bool
	{
		return array_key_exists($name, $this->tags);
	}

	public function addTag(string $name, mixed $value = null): void
	{
		$this->tags[$name] = $value;
	}

	/**
	 * @param array<string, mixed> $tags
	 */
	public function setTags(array $tags): void
	{
		$this->tags = $tags;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getOpenApi(): array
	{
		return $this->openApi;
	}

	/**
	 * @param array<string, mixed> $openApi
	 */
	public function setOpenApi(array $openApi): void
	{
		$this->openApi = $openApi;
	}

	public function getSummary(): string|null
	{
		return $this->summary;
	}

	public function setSummary(string|null $summary): void
	{
		$this->summary = $summary;
	}

	public function isDeprecated(): bool
	{
		return $this->deprecated;
	}

	public function setDeprecated(bool $deprecated): void
	{
		$this->deprecated = $deprecated;
	}

}
