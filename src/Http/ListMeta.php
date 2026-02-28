<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use JsonSerializable;

/**
 * Pagination metadata for list responses.
 */
final class ListMeta implements JsonSerializable
{

	public function __construct(
		public readonly int $totalCount,
		public readonly int $limit,
		public readonly int $offset,
	)
	{
	}

	/**
	 * @return array{totalCount: int, limit: int, offset: int}
	 */
	public function toArray(): array
	{
		return [
			'totalCount' => $this->totalCount,
			'limit' => $this->limit,
			'offset' => $this->offset,
		];
	}

	/**
	 * @return array{totalCount: int, limit: int, offset: int}
	 */
	public function jsonSerialize(): array
	{
		return $this->toArray();
	}

}
