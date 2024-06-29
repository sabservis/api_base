<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

interface TypeNormalizer
{

	public function normalize(mixed $value): mixed;

	public function denormalize(mixed $value): mixed;

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array;

}
