<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Validator;

use Pocta\DataMapper\Validation\Validator;
use Sabservis\Api\Exception\Api\ValidationException;
use function is_array;
use function is_string;

final class DataMapperEntityValidator implements EntityValidator
{

	public function __construct(private readonly Validator $validator)
	{
	}

	public function validate(object $entity, array|string|null $validationGroups = null): void
	{
		$groups = match (true) {
			is_string($validationGroups) => [$validationGroups],
			is_array($validationGroups) => $validationGroups,
			default => null,
		};

		$errors = $this->validator->validate($entity, throw: false, groups: $groups);

		if ($errors === []) {
			return;
		}

		$fields = [];

		foreach ($errors as $path => $message) {
			$fields[$path] = [$message];
		}

		throw (new ValidationException())->withFields($fields);
	}

}
