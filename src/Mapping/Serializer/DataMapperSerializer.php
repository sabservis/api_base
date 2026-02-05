<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Serializer;

use JsonException;
use Pocta\DataMapper\Exceptions\ValidationException as DataMapperValidationException;
use Pocta\DataMapper\Mapper;
use Pocta\DataMapper\MapperOptions;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Utils\JsonLimits;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

class DataMapperSerializer implements EntitySerializer
{

	/**
	 * Default JSON decoding depth limit.
	 *
	 * @deprecated Use JsonLimits::DEFAULT_DEPTH instead
	 */
	public const DEFAULT_JSON_DEPTH = JsonLimits::DEFAULT_DEPTH;

	private Mapper $mapper;

	/**
	 * @param int<1, max> $jsonDepth
	 */
	public function __construct(MapperOptions|null $options = null, private int $jsonDepth = self::DEFAULT_JSON_DEPTH)
	{
		$this->mapper = new Mapper($options ?? MapperOptions::production());
	}

	public function serialize(mixed $data): string
	{
		if (is_object($data)) {
			return $this->mapper->toJson($data);
		}

		return json_encode($data, JSON_THROW_ON_ERROR);
	}

	public function deserialize(mixed $data, mixed $type = null): mixed
	{
		if (!is_string($data)) {
			throw new ClientErrorException('Invalid input data - expected JSON string');
		}

		if ($type === null) {
			try {
				return json_decode($data, true, $this->jsonDepth, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new ClientErrorException('Invalid input data (JSON)', previous: $e);
			}
		}

		try {
			/** @var class-string $type */
			return $this->mapper->fromJson($data, $type);
		} catch (DataMapperValidationException $e) {
			throw (new ValidationException(
				'Request body contains validation errors',
				previous: $e,
			))->withFields($e->getErrors());
		} catch (JsonException $e) {
			throw new ClientErrorException('Invalid input data (JSON)', previous: $e);
		}
	}

}
