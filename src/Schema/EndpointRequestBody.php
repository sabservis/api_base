<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use Sabservis\Api\Attribute\OpenApi\FileUpload;

class EndpointRequestBody
{

	/**
	 * Default allowed content types for JSON body deserialization.
	 */
	public const DEFAULT_JSON_CONTENT_TYPES = ['application/json'];

	private string|null $description = null;

	private string|null $entity = null;

	private bool $required = false;

	/** @var array<string, mixed>|null Inline content spec for non-entity request bodies */
	private array|null $contentSpec = null;

	/** @var array<FileUpload> File upload definitions */
	private array $fileUploads = [];

	/**
	 * Allowed Content-Type values for this request body.
	 * Default is ['application/json'] when entity is set.
	 *
	 * @var array<string>|null null means use default based on context
	 */
	private array|null $allowedContentTypes = null;

	public function getDescription(): string|null
	{
		return $this->description;
	}

	public function setDescription(string|null $description): void
	{
		$this->description = $description;
	}

	public function getEntity(): string|null
	{
		return $this->entity;
	}

	public function setEntity(string|null $entity): void
	{
		$this->entity = $entity;
	}

	public function isRequired(): bool
	{
		return $this->required;
	}

	public function setRequired(bool $required): void
	{
		$this->required = $required;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getContentSpec(): array|null
	{
		return $this->contentSpec;
	}

	/**
	 * @param array<string, mixed>|null $contentSpec
	 */
	public function setContentSpec(array|null $contentSpec): void
	{
		$this->contentSpec = $contentSpec;
	}

	/**
	 * @return array<FileUpload>
	 */
	public function getFileUploads(): array
	{
		return $this->fileUploads;
	}

	/**
	 * @param array<FileUpload> $fileUploads
	 */
	public function setFileUploads(array $fileUploads): void
	{
		$this->fileUploads = $fileUploads;
	}

	/**
	 * Check if this request body expects file uploads.
	 */
	public function hasFileUploads(): bool
	{
		return $this->fileUploads !== [];
	}

	/**
	 * Get file upload definition by field name.
	 */
	public function getFileUpload(string $name): FileUpload|null
	{
		foreach ($this->fileUploads as $upload) {
			if ($upload->name === $name) {
				return $upload;
			}
		}

		return null;
	}

	/**
	 * Get allowed Content-Type values.
	 *
	 * Returns explicitly set types, or defaults based on context:
	 * - If entity is set: ['application/json']
	 * - Otherwise: null (no validation)
	 *
	 * @return array<string>|null
	 */
	public function getAllowedContentTypes(): array|null
	{
		if ($this->allowedContentTypes !== null) {
			return $this->allowedContentTypes;
		}

		// Default to JSON types when entity deserialization is expected
		if ($this->entity !== null) {
			return self::DEFAULT_JSON_CONTENT_TYPES;
		}

		return null;
	}

	/**
	 * Set allowed Content-Type values for this request body.
	 *
	 * @param array<string>|null $contentTypes List of allowed MIME types (e.g., ['application/json'])
	 */
	public function setAllowedContentTypes(array|null $contentTypes): void
	{
		$this->allowedContentTypes = $contentTypes;
	}

}
