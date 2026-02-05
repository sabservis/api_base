<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use function in_array;
use function sprintf;
use function strtoupper;

/**
 * Route definition - contains routing-related data for URL matching.
 */
final class RouteDefinition
{

	public const METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH', 'HEAD'];

	/** @var array<string> */
	private array $methods = [];

	private string|null $mask = null;

	private string|null $pattern = null;

	private string|null $rawPattern = null;

	/**
	 * @return array<string>
	 */
	public function getMethods(): array
	{
		return $this->methods;
	}

	/**
	 * @param array<string> $methods
	 */
	public function setMethods(array $methods): void
	{
		foreach ($methods as $method) {
			$this->addMethod($method);
		}
	}

	public function addMethod(string $method): void
	{
		$method = strtoupper($method);

		if (!in_array($method, self::METHODS, true)) {
			throw new InvalidArgumentException(sprintf('Method %s is not allowed', $method));
		}

		$this->methods[] = $method;
	}

	public function hasMethod(string $method): bool
	{
		return in_array(strtoupper($method), $this->methods, true);
	}

	public function getMask(): string|null
	{
		return $this->mask;
	}

	public function setMask(string|null $mask): void
	{
		$this->mask = $mask;
	}

	public function getPattern(): string
	{
		if ($this->pattern === null) {
			$this->pattern = $this->generatePattern();
		}

		return $this->pattern;
	}

	public function setPattern(string|null $pattern): void
	{
		$this->pattern = $pattern;
	}

	public function setRawPattern(string|null $rawPattern): void
	{
		$this->rawPattern = $rawPattern;
	}

	public function getRawPattern(): string|null
	{
		return $this->rawPattern;
	}

	private function generatePattern(): string
	{
		if ($this->rawPattern === null) {
			throw new InvalidStateException('Pattern attribute is required');
		}

		return sprintf('#^%s$#', $this->rawPattern);
	}

}
