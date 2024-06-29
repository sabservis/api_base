<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Serializer;

class NullSerializer implements EntitySerializer
{

	public function serialize(mixed $data): mixed
	{
		return (string) $data;
	}

	public function deserialize(mixed $data, mixed $type = null): mixed
	{
		return $data;
	}

}
