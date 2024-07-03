<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Serializer;

use Sabservis\Api\Exception\Api\ValidationException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
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

			assert(is_object($dto));

		} catch (ExtraAttributesException $e) {
			throw ValidationException::create()
				->withMessage($e->getMessage());
		}

		return $dto;
	}

}
