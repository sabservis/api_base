<?php declare(strict_types = 1);

namespace Sabservis\Api\Http\Response;

use Sabservis\Api\Attribute as OA;
use Sabservis\Api\Mapping\Entity\AbstractEntity;

#[OA\Schema]
class ApiListMetaResponse extends AbstractEntity
{

	public function __construct(
		#[OA\Property]
		public int $totalCount,
		#[OA\Property]
		public int|null $limit = null,
		#[OA\Property]
		public int|null $offset = null,
	)
	{
	}

}
