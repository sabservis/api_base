<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

class EndpointParameter
{

	public const TypeString = 'string';

	public const TypeInteger = 'int';

	public const TypeFloat = 'float';

	public const TypeBoolean = 'boolean';

	public const TypeDateTime = 'datetime';

	public const InQuery = 'query';

	public const InCookie = 'cookie';

	public const InHeader = 'header';

	public const InPath = 'path';

	private string|null $description = null;

	private string $in = self::InPath;

	private bool $required = true;

	private bool $deprecated = false;

	private bool $allowEmptyValue = false;

	private mixed $example = null;

	private string|null $style = null;

	private bool|null $explode = null;

	/** @var array<string, mixed>|null */
	private array|null $schemaSpec = null;

	/** @var class-string|null Original class reference for schema registration */
	private string|null $schemaRef = null;

	public function __construct(
		private string $name,
		private string $type = self::TypeString,
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getDescription(): string|null
	{
		return $this->description;
	}

	public function setDescription(string|null $description): void
	{
		$this->description = $description;
	}

	public function getIn(): string
	{
		return $this->in;
	}

	public function setIn(string $in): void
	{
		$this->in = $in;
	}

	public function isRequired(): bool
	{
		return $this->required;
	}

	public function setRequired(bool $required): void
	{
		$this->required = $required;
	}

	public function isDeprecated(): bool
	{
		return $this->deprecated;
	}

	public function setDeprecated(bool $deprecated): void
	{
		$this->deprecated = $deprecated;
	}

	public function isAllowEmptyValue(): bool
	{
		return $this->allowEmptyValue;
	}

	public function setAllowEmptyValue(bool $allowEmptyValue): void
	{
		$this->allowEmptyValue = $allowEmptyValue;
	}

	public function getExample(): mixed
	{
		return $this->example;
	}

	public function setExample(mixed $example): void
	{
		$this->example = $example;
	}

	public function getStyle(): string|null
	{
		return $this->style;
	}

	public function setStyle(string|null $style): void
	{
		$this->style = $style;
	}

	public function getExplode(): bool|null
	{
		return $this->explode;
	}

	public function setExplode(bool|null $explode): void
	{
		$this->explode = $explode;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getSchemaSpec(): array|null
	{
		return $this->schemaSpec;
	}

	/**
	 * @param array<string, mixed>|null $schemaSpec
	 */
	public function setSchemaSpec(array|null $schemaSpec): void
	{
		$this->schemaSpec = $schemaSpec;
	}

	/**
	 * @return class-string|null
	 */
	public function getSchemaRef(): string|null
	{
		return $this->schemaRef;
	}

	/**
	 * @param class-string|null $schemaRef
	 */
	public function setSchemaRef(string|null $schemaRef): void
	{
		$this->schemaRef = $schemaRef;
	}

}
