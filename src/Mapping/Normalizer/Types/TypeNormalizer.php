<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeExceptionOld;

interface TypeNormalizer
{

	/**
	 * @throws InvalidArgumentTypeExceptionOld
	 */
//	public function denormalize(mixed $value): mixed;

	// public function normalize(mixed $value): mixed;

	public static function getSupportedTypes(): array;

}
