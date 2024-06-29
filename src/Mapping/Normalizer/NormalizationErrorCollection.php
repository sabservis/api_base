<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer;

use function count;

class NormalizationErrorCollection
{

	/** @var list<array{field: string, message: string}> */
	private array $errors = [];

	public function addError(string $field, string $message): void
	{
		$this->errors[] = [
			'field' => $field,
			'message' => $message,
		];
	}

	/**
	 * @return list<array{field: string, message: string}>
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	public function hasErrors(): bool
	{
		return count($this->errors) > 0;
	}

}
