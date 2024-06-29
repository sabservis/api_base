<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer;

use Sabservis\Api\Mapping\Normalizer\Types\TypeNormalizer;

interface NormalizerProvider
{

	public function addNormalizer(string $normalizer): void;

	public function findNormalizer(string $type): TypeNormalizer|null;

}
