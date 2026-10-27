<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use JsonSerializable;
use function array_values;

/**
 * Response wrapper for list/collection endpoints without pagination.
 *
 * Serializes as plain JSON array: [...]
 *
 * @template T
 */
final class ListResponse implements JsonSerializable
{

	/** @var list<T> */
	private array $data;

	/** @param array<T> $data */
	public function __construct(array $data)
	{
		// Reindex - array_filter() etc. leave gaps and json_encode() would emit an object
		$this->data = array_values($data);
	}

	/**
	 * @return array<T>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * @return array<T>
	 */
	public function jsonSerialize(): array
	{
		return $this->data;
	}

}
