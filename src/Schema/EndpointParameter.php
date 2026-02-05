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

}
