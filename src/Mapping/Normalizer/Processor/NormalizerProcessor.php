<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Processor;

interface NormalizerProcessor
{

	public function normalize(mixed $data): mixed;

	/**
	 * @template T of object
	 * @param class-string<T>|null $class
	 */
	public function denormalize(mixed $data, string|null $class): mixed;

}
