<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

/**
 * Response wrapper for list/collection endpoints with pagination metadata.
 *
 * Serializes as: { "data": [...], "meta": { "total": 100, "limit": 20, "offset": 0 } }
 *
 * @template T
 */
final class PaginatedListResponse
{

	/**
	 * @param array<T> $data
	 */
	public function __construct(
		private array $data,
		private ListMeta|null $meta = null,
	)
	{
	}

	/**
	 * @template TItem
	 * @param array<TItem> $data
	 * @return self<TItem>
	 */
	public static function create(array $data, int $total, int $limit, int $offset): self
	{
		return new self($data, new ListMeta($total, $limit, $offset));
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

}
