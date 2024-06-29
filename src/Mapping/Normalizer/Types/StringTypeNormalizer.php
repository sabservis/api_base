<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

class StringTypeNormalizer extends AbstractTypeNormalizer
{

	public function denormalize(mixed $value): string|null
	{
		return (string) $value;
	}

	public function normalize(mixed $value): string
	{
		return (string) $value;
	}

	/**
	 * @return array<string>
	 */
	public static function getSupportedTypes(): array
	{
		return ['string', 'text'];
	}

}
