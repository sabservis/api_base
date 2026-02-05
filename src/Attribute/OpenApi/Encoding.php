<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Define encoding for a specific property in multipart/form-data request body.
 *
 * Used to specify content type, headers, and serialization style for form fields.
 *
 * Usage:
 *   #[FileUpload(name: 'document')]
 *   #[Encoding(property: 'document', contentType: 'application/pdf')]
 *
 *   #[RequestBody(ref: FormDto::class)]
 *   #[Encoding(property: 'metadata', contentType: 'application/json')]
 *   #[Encoding(property: 'tags', style: 'form', explode: true)]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Encoding implements OpenApiAttributeInterface
{

	/**
	 * @param string $property The property name this encoding applies to
	 * @param string|null $contentType Content-Type for this property (e.g., 'application/json', 'image/png')
	 * @param array<string, array<string, mixed>>|null $headers Additional headers for this property
	 * @param string|null $style How the property is serialized (form, spaceDelimited, pipeDelimited, deepObject)
	 * @param bool|null $explode Whether arrays/objects generate separate parameters
	 * @param bool|null $allowReserved Allow reserved characters without percent-encoding
	 */
	public function __construct(
		public string $property,
		public string|null $contentType = null,
		public array|null $headers = null,
		public string|null $style = null,
		public bool|null $explode = null,
		public bool|null $allowReserved = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->contentType !== null) {
			$spec['contentType'] = $this->contentType;
		}

		if ($this->headers !== null) {
			$spec['headers'] = $this->headers;
		}

		if ($this->style !== null) {
			$spec['style'] = $this->style;
		}

		if ($this->explode !== null) {
			$spec['explode'] = $this->explode;
		}

		if ($this->allowReserved !== null) {
			$spec['allowReserved'] = $this->allowReserved;
		}

		return $spec;
	}

	public function getProperty(): string
	{
		return $this->property;
	}

}
