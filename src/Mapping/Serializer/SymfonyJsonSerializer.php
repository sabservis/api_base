<?php

declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Serializer;

use Sabservis\Api\Exception\Api\ValidationException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;

class SymfonyJsonSerializer implements EntitySerializer
{

	public function __construct(protected SerializerInterface $serializer)
	{
	}

	public function serialize(mixed $data): string
	{
		return $this->serializer->serialize($data, 'json');
	}

	public function deserialize(mixed $data, mixed $type = null): mixed
	{
		try {
			$dto = $this->serializer->deserialize($data, $type, 'json', [
				'allow_extra_attributes' => false,
			]);

			assert($dto instanceof $type);

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

		return $dto;
	}

}
