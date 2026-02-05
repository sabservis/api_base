<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Marks endpoint as accepting file upload(s) via multipart/form-data.
 *
 * Usage:
 *   Single file:
 *     #[FileUpload(name: 'avatar')]
 *
 *   Multiple files:
 *     #[FileUpload(name: 'documents', multiple: true)]
 *
 *   Multiple different files:
 *     #[FileUpload(name: 'avatar')]
 *     #[FileUpload(name: 'cover')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class FileUpload implements OpenApiAttributeInterface
{

	/**
	 * @param string $name Form field name for the file input
	 * @param bool $multiple Whether multiple files can be uploaded for this field
	 * @param bool $required Whether this file is required
	 * @param string|null $description Description for OpenAPI docs
	 * @param array<string>|null $allowedTypes Allowed MIME types (e.g., ['image/jpeg', 'image/png'])
	 */
	public function __construct(
		public string $name,
		public bool $multiple = false,
		public bool $required = true,
		public string|null $description = null,
		public array|null $allowedTypes = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$property = [
			'type' => 'string',
			'format' => 'binary',
		];

		if ($this->description !== null) {
			$property['description'] = $this->description;
		}

		if ($this->multiple) {
			return [
				'type' => 'array',
				'items' => $property,
				'description' => $this->description,
			];
		}

		return $property;
	}

}
