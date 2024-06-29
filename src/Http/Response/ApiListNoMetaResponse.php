<?php declare(strict_types = 1);

namespace Sabservis\Api\Http\Response;

use Sabservis\Api\Mapping\Entity\AbstractEntity;

class ApiListNoMetaResponse extends AbstractEntity
{

	/**
	 * @param array<mixed> $data
	 */
	public function __construct(public array $data)
	{
	}

}
