<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class MediaType implements OpenApiAttributeInterface
{

	public function __construct(
		public string $mediaType = 'application/json',
		public Schema|null $schema = null,
		public mixed $example = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->schema !== null) {
			$spec['schema'] = $this->schema->toOpenApiSpec();
		}

		if ($this->example !== null) {
			$spec['example'] = $this->example;
		}

		return [$this->mediaType => $spec];
	}

}
