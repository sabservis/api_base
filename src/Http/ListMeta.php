<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use JsonSerializable;

/**
 * Pagination metadata for list responses.
 */
final class ListMeta implements JsonSerializable
{

	public function __construct(
		public readonly int $total,
		public readonly int $limit,
		public readonly int $offset,
	)
	{
	}

	/**
	 * @return array{total: int, limit: int, offset: int}
	 */
	public function toArray(): array
	{
		return [
			'total' => $this->total,
			'limit' => $this->limit,
			'offset' => $this->offset,
		];
	}

	/**
	 * @return array{total: int, limit: int, offset: int}
	 */
	public function jsonSerialize(): array
	{
		return $this->toArray();
	}

}
