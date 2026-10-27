<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use JsonSerializable;
use function array_values;

/**
 * Response wrapper for list/collection endpoints with data wrapper.
 *
 * Without meta: { "data": [...] }
 * With meta: { "data": [...], "meta": { "totalCount": 100, "limit": 20, "offset": 0 } }
 *
 * @template T
 */
final class PaginatedListResponse implements JsonSerializable
{

	/** @var list<T> */
	private array $data;

	/**
	 * @param array<T> $data
	 */
	public function __construct(
		array $data,
		private ListMeta|null $meta = null,
	)
	{
		// Reindex - array_filter() etc. leave gaps and json_encode() would emit an object
		$this->data = array_values($data);
	}

	/**
	 * @template TItem
	 * @param array<TItem> $data
	 * @return self<TItem>
	 */
	public static function create(array $data, int $totalCount, int $limit, int $offset): self
	{
		return new self($data, new ListMeta($totalCount, $limit, $offset));
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

	/**
	 * @return array{data: array<T>}|array{data: array<T>, meta: array{totalCount: int, limit: int, offset: int}}
	 */
	public function jsonSerialize(): array
	{
		$result = ['data' => $this->data];

		if ($this->meta !== null) {
			$result['meta'] = $this->meta->toArray();
		}

		return $result;
	}

}
