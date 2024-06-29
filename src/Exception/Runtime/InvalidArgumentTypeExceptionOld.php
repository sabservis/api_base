<?php declare(strict_types = 1);

namespace Sabservis\Api\Exception\Runtime;

use Sabservis\Api\Exception\RuntimeException;

class InvalidArgumentTypeExceptionOld extends RuntimeException
{

	public const TYPE_STRING = 'string';

	public const TYPE_INTEGER = 'integer';

	public const TYPE_NUMBER = 'number';

	public const TYPE_BOOLEAN = 'boolean';

	public const TYPE_ARRAY = 'array';

	public const TYPE_DATETIME = 'datetime';

	public const TYPE_BACKEDENUM = 'backed_enum';

	public function __construct(
		private string $type,
		private string|null $description = null,
	)
	{
		parent::__construct();
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getDescription(): string|null
	{
		return $this->description;
	}

}
