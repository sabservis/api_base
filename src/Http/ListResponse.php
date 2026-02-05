<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use JsonSerializable;

/**
 * Response wrapper for list/collection endpoints.
 *
 * Supports two modes:
 * - With meta: { "data": [...], "meta": { "total": 100, "limit": 20, "offset": 0 } }
 * - Without meta: [...]
 *
 * @template T
 */
final class ListResponse implements JsonSerializable
{

	/**
	 * @param array<T> $data
	 */
	private function __construct(
		private array $data,
		private ListMeta|null $meta,
	)
	{
	}

	/**
	 * Create a list response with pagination metadata.
	 *
	 * @template TItem
	 * @param array<TItem> $data
	 * @return self<TItem>
	 */
	public static function create(array $data, int $total, int $limit, int $offset): self
	{
		return new self($data, new ListMeta($total, $limit, $offset));
	}

	/**
	 * Create a list response without metadata (just array).
	 *
	 * @template TItem
	 * @param array<TItem> $data
	 * @return self<TItem>
	 */
	public static function withoutMeta(array $data): self
	{
		return new self($data, null);
	}

	/**
	 * @return array<T>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	public function getMeta(): ListMeta|null
	{
		return $this->meta;
	}

	public function hasMeta(): bool
	{
		return $this->meta !== null;
	}

	/**
	 * @return array<T>|array{data: array<T>, meta: array{total: int, limit: int, offset: int}}
	 */
	public function toArray(): array
	{
		if ($this->meta === null) {
			return $this->data;
		}

		return [
			'data' => $this->data,
			'meta' => $this->meta->toArray(),
		];
	}

	/**
	 * @return array<T>|array{data: array<T>, meta: array{total: int, limit: int, offset: int}}
	 */
	public function jsonSerialize(): array
	{
		return $this->toArray();
	}

}
