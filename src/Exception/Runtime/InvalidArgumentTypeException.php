<?php declare(strict_types = 1);

namespace Sabservis\Api\Exception\Runtime;

use Sabservis\Api\Exception\RuntimeException;

class InvalidArgumentTypeException extends RuntimeException
{

	public function __construct(
		public string $type,
		public string|null $description = null,
		public string|null $parameter = null,
		public mixed $value = null,
	)
	{
		parent::__construct(message: $this->description ?? 'Invalid argument type.');
	}

}
