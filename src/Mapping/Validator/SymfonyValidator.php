<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Validator;

use Sabservis\Api\Exception\Api\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function count;

class SymfonyValidator implements EntityValidator
{

	public function __construct(protected ValidatorInterface $validator)
	{
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function validate(object $entity): void
	{
		// Try to validate entity only if its enabled
		$violations = $this->validator->validate($entity);

		if (count($violations) > 0) {
			$fields = [];

			foreach ($violations as $violation) {
				$fields[$violation->getPropertyPath()][] = $violation->getMessage();
			}

			throw ValidationException::create()
				->withMessage('Invalid request data')
				->withFields($fields);
		}
	}

}
