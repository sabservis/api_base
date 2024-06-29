<?php declare(strict_types = 1);

namespace Sabservis\Api\Http\Response;

use Sabservis\Api\Attribute as OA;
use Sabservis\Api\Mapping\Entity\AbstractEntity;

#[OA\Schema]
class ApiListWithMetaResponse extends AbstractEntity
{

	/**
	 * @param array<mixed> $data
	 */
	public function __construct(
		#[OA\Property]
		public array $data,
		#[OA\Property(ref: ApiListMetaResponse::class)]
		public ApiListMetaResponse $meta,
	)
	{
	}

}
