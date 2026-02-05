<?php declare(strict_types = 1);

namespace Sabservis\Api\Exception;

use Exception;

/**
 * @mixin Exception
 */
trait ExceptionExtra
{

	protected mixed $context = null;

	/**
	 * @return static
	 */
	public static function create(): static
	{
		return new static();
	}

	/**
	 * @param string|array<string> $message
	 * @return static
	 */
	public function withMessage(string|array $message): static
	{
		$this->message = $message;

		return $this;
	}

	/**
	 * @return static
	 */
	public function withContext(mixed $context): static
	{
		$this->context = $context;

		return $this;
	}

	/**
	 * @return static
	 */
	public function withTypedContext(string $type, mixed $context): static
	{
		$this->context = [$type => $context];

		return $this;
	}

	public function getContext(): mixed
	{
		return $this->context;
	}

}
