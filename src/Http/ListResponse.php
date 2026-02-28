<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use JsonSerializable;

/**
 * Response wrapper for list/collection endpoints without pagination.
 *
 * Serializes as plain JSON array: [...]
 *
 * @template T
 */
final class ListResponse implements JsonSerializable
{

	/** @param array<T> $data */
	public function __construct(private array $data)
	{
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
