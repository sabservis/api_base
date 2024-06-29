<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer;

use Sabservis\Api\Mapping\Normalizer\Types\AbstractTypeNormalizer;
use Sabservis\Api\Mapping\Normalizer\Types\TypeNormalizer;
use function assert;

class TypeNormalizerProvider implements NormalizerProvider
{

	/** @var list<TypeNormalizer> */
	public array $normalizers = [];

	/**
	 * @template T of TypeNormalizer
	 *
	 * @var array<string, TypeNormalizer>
	 */
	private array $normalizerCache = [];

	/**
	 * @template T of TypeNormalizer
	 * @param class-string<T> $normalizer
	 */
	public function addNormalizer(string $normalizer): void
	{
		$normalizer = new $normalizer();
		assert($normalizer instanceof AbstractTypeNormalizer);
		$normalizer->setProvider($this);

		$this->normalizers[] = $normalizer;
	}

	public function findNormalizer(string $type): TypeNormalizer|null
	{
		if (isset($this->normalizerCache[$type])) {
			return $this->normalizerCache[$type];
		}

		foreach ($this->normalizers as $normalizer) {
			foreach ($normalizer::getSupportedTypes() as $supportedType) {
				if ($supportedType === $type) {
					if ($normalizer instanceof TypeNormalizer) {
						$inst = $normalizer;
						$this->normalizerCache[$type] = $inst;

						return $inst;
					}
				}
			}
		}

		return null;
	}

}
