<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Serializer;

use JsonException;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Mapping\Normalizer\Processor\NormalizerProcessor;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

class JsonSerializer implements EntitySerializer
{

	public function __construct(protected NormalizerProcessor $processor)
	{
	}

	public function serialize(mixed $data): string
	{
		$data = $this->processor->normalize($data);

		return json_encode($data, JSON_THROW_ON_ERROR);
	}

	public function deserialize(mixed $data, mixed $type = null): mixed
	{
		try {
			$data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ClientErrorException('Invalid input data (JSON)', previous: $e);
		}

		return $this->processor->denormalize($data, $type);
	}

}
