<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Processor;

use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Mapping\Entity\AbstractEntity;
use Sabservis\Api\Mapping\Normalizer\NormalizationErrorCollection;
use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Types\ObjectTypeNormalizer;
use Sabservis\Api\Mapping\Normalizer\Types\TypeNormalizer;
use function array_reduce;
use function assert;
use function class_exists;
use function gettype;
use function is_scalar;

class MainNormalizerProcessor implements NormalizerProcessor
{

	public const TypeObject = 'object';

	public function __construct(protected NormalizerProvider $provider)
	{
	}

	public function normalize(mixed $data): mixed
	{
		if ($data instanceof AbstractEntity) {
			$normalizer = $this->provider->findNormalizer(self::TypeObject);
			assert($normalizer instanceof ObjectTypeNormalizer);
			$data = $normalizer->normalize($data);
		}

		if (is_scalar($data) === true) {
			$normalizer = $this->provider->findNormalizer(gettype($data));
			assert($normalizer instanceof TypeNormalizer);

			return $normalizer->normalize($data);
		}

		return $data;
	}

	/**
	 * @template T of object
	 * @param class-string<T>|null $class
	 */
	public function denormalize(mixed $data, string|null $class): mixed
	{
		if ($data === null) {
			return null;
		}

		$errorCollection = new NormalizationErrorCollection();

		if (is_scalar($data) === true) {
			$normalizer = $this->provider->findNormalizer(gettype($data));
			assert($normalizer instanceof TypeNormalizer);

			return $normalizer->denormalize($data);
		}

		if (class_exists((string) $class) === true) {

			$objectNormalizer = $this->provider->findNormalizer(self::TypeObject);
			assert($objectNormalizer instanceof ObjectTypeNormalizer);
			$objectNormalizer->setErrorCollection($errorCollection);
			$result = $objectNormalizer->denormalize($data, $class);

			if ($errorCollection->hasErrors()) {
				throw new ValidationException('Invalid request data', fields: [
					// Now it looks like the Symfony validation error format
					'validation' => array_reduce($errorCollection->getErrors(), static function ($carry, $error) {
						$carry[$error['field']][] = $error['message'];

						return $carry;
					}, []),
				]);
			}

			return $result;
		}

		return $data;
	}

}
