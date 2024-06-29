<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Validator;

use Sabservis\Api\Exception\Api\ValidationException;

interface EntityValidator
{

	/**
	 * @param array<mixed>|string|null $validationGroups
	 * @throws ValidationException
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function validate(object $entity, array|string|null $validationGroups = null): void;

}
