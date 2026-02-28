<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use function is_array;

class EndpointResponse
{

	public const WrapperListWithMeta = 'list_with_meta';

	public const WrapperListNoMeta = 'list_no_meta';

	public const WrapperListDataOnly = 'list_data_only';

	/** @var string|array<string>|null */
	private string|array|null $entity = null;

	private string|null $wrapperType = null;

	private string|null $fileContentType = null;

	/** @var array<string, mixed>|null Inline content spec for non-entity responses */
	private array|null $contentSpec = null;

	/** @var array<class-string> Classes referenced in contentSpec that need to be registered */
	private array $referencedSchemas = [];

	public function __construct(
		private string $code,
		private string $description,
	)
	{
	}

	/**
	 * @param string|array<string>|null $entity
	 */
	public function setEntity(string|array|null $entity): void
	{
		$this->entity = $entity;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	public function getCode(): string
	{
		return $this->code;
	}

	/**
	 * @return string|array<string>|null
	 */
	public function getEntity(): string|array|null
	{
		return $this->entity;
	}

	/**
	 * Check if entity is a oneOf (array of types).
	 */
	public function isEntityOneOf(): bool
	{
		return is_array($this->entity);
	}

	public function setWrapperType(string|null $wrapperType): void
	{
		$this->wrapperType = $wrapperType;
	}

	public function getWrapperType(): string|null
	{
		return $this->wrapperType;
	}

	public function isListWithMeta(): bool
	{
		return $this->wrapperType === self::WrapperListWithMeta;
	}

	public function isListNoMeta(): bool
	{
		return $this->wrapperType === self::WrapperListNoMeta;
	}

	public function isListDataOnly(): bool
	{
		return $this->wrapperType === self::WrapperListDataOnly;
	}

	public function setFileContentType(string|null $contentType): void
	{
		$this->fileContentType = $contentType;
	}

	public function getFileContentType(): string|null
	{
		return $this->fileContentType;
	}

	public function isFileResponse(): bool
	{
		return $this->fileContentType !== null;
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
	 * @return array<class-string>
	 */
	public function getReferencedSchemas(): array
	{
		return $this->referencedSchemas;
	}

	/**
	 * @param array<class-string> $referencedSchemas
	 */
	public function setReferencedSchemas(array $referencedSchemas): void
	{
		$this->referencedSchemas = $referencedSchemas;
	}

}
