<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Spec;

use JsonSerializable;

final class ResponseObject implements JsonSerializable
{

	/**
	 * @param array<string, MediaTypeObject>|null $content
	 * @param array<string, HeaderObject>|null $headers
	 * @param array<string, LinkObject>|null $links
	 */
	public function __construct(
		public string $description,
		public array|null $content = null,
		public array|null $headers = null,
		public array|null $links = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$data = ['description' => $this->description];

		if ($this->content !== null && $this->content !== []) {
			$data['content'] = $this->content;
		}

		if ($this->headers !== null && $this->headers !== []) {
			$data['headers'] = $this->headers;
		}

		if ($this->links !== null && $this->links !== []) {
			$data['links'] = $this->links;
		}

		return $data;
	}

}
