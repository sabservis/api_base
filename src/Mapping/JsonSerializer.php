<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping;

use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class JsonSerializer implements EntitySerializer
{

	protected NormalizerProvider|null $normalizer;

	public function addNormalizer(NormalizerProvider $normalizer): void
	{
		$this->normalizer = $normalizer;
	}

	public function serialize(mixed $data): mixed
	{

		if ($data instanceof AbstractEntity) {
			$normalizer = $this->normalizer->findNormalizer('object');

			return $normalizer->normalize($data);
		}
		return $data;
	}

	public function deserialize(mixed $data, mixed $type = null): mixed
	{
		try {
			if (class_exists($type)) {
				$normalizer = $this->normalizer->findNormalizer('object');

				return $normalizer->denormalize($data, $type);
			}

			return $data;
		} catch (NotNormalizableValueException $e) {

			$message = $e->getMessage();
			$message = preg_replace('/ for class\s+"[^"]+"/', '', $message);

			preg_match('/The type of the "([^"]+)" attribute/', $e->getMessage(), $matches);
			$key = $matches[1] ?? 0;
			$fields = [$key => $message];

			throw ValidationException::create()
				->withMessage('Invalid request data')
				->withFields($fields);
		} catch (ExtraAttributesException $e) {
			throw ValidationException::create()
				->withMessage($e->getMessage());
		} catch (\Throwable $e) {
			throw ValidationException::create()
				->withMessage($e->getMessage());
		}
	}

}
