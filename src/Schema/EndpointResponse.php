<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

class EndpointResponse
{

	public const WrapperListWithMeta = 'list_with_meta';

	public const WrapperListNoMeta = 'list_no_meta';

	private string|null $entity = null;

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

	public function setEntity(string|null $entity): void
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

	public function getEntity(): string|null
	{
		return $this->entity;
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
