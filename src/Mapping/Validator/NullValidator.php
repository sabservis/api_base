<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Validator;

class NullValidator implements EntityValidator
{

	/**
	 * @param array<mixed>|string|null $validationGroups
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function validate(object $entity, array|string|null $validationGroups = null): void
	{
		// Hell nothing...
	}

}
