<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Serializer;

use Sabservis\Api\Exception\Api\ValidationException;

interface EntitySerializer
{

	/**
	 * @throws ValidationException
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function serialize(mixed $data): mixed;

	/**
	 * @throws ValidationException
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function deserialize(mixed $data, mixed $type = null): mixed;

}
