<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use function implode;
use function md5;
use function sort;

class Schema
{

	/** @var array<Endpoint> */
	private array $endpoints = [];

	/** @var array<string, array{name: string, description: string|null}> */
	private array $tags = [];

	private string|null $hash = null;

	public function addEndpoint(Endpoint $endpoint): void
	{
		$this->endpoints[] = $endpoint;
	}

	/**
	 * @return array<Endpoint>
	 */
	public function getEndpoints(): array
	{
		return $this->endpoints;
	}

	public function addTag(string $name, string|null $description = null): void
	{
		// Only add if not exists or if new one has description and old one doesn't
		if (!isset($this->tags[$name]) || ($description !== null && $this->tags[$name]['description'] === null)) {
			$this->tags[$name] = [
				'name' => $name,
				'description' => $description,
			];
		}
	}

	/**
	 * @return array<string, array{name: string, description: string|null}>
	 */
	public function getTags(): array
	{
		return $this->tags;
	}

	/**
	 * Returns a hash of the schema for cache invalidation.
	 * Hash is based on endpoint masks and methods.
	 */
	public function getHash(): string
	{
		if ($this->hash !== null) {
			return $this->hash;
		}

		$data = [];

		foreach ($this->endpoints as $endpoint) {
			$data[] = $endpoint->getMask() . ':' . implode(',', $endpoint->getMethods());
		}

		sort($data);

		$this->hash = md5(implode('|', $data));

		return $this->hash;
	}

}
