<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Define media type for response or request body.
 *
 * Usage:
 *   Single example:
 *     #[MediaType(mediaType: 'application/json', example: ['id' => 1, 'name' => 'John'])]
 *
 *   Multiple named examples:
 *     #[MediaType(
 *         mediaType: 'application/json',
 *         examples: [
 *             new Examples(example: 'success', summary: 'Successful response', value: ['id' => 1]),
 *             new Examples(example: 'empty', summary: 'Empty response', value: []),
 *         ]
 *     )]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class MediaType implements OpenApiAttributeInterface
{

	use ExamplesSupport;

	/**
	 * @param string $mediaType MIME type (e.g., 'application/json')
	 * @param Schema|null $schema Schema for this media type
	 * @param mixed $example Single example value
	 * @param array<Examples>|null $examples Named examples map
	 */
	public function __construct(
		public string $mediaType = 'application/json',
		public Schema|null $schema = null,
		public mixed $example = null,
		public array|null $examples = null,
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

		$examplesMap = $this->buildExamplesMap($this->examples);

		if ($examplesMap !== []) {
			$spec['examples'] = $examplesMap;
		}

		return [$this->mediaType => $spec];
	}

}
