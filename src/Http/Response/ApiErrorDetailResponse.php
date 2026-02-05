<?php declare(strict_types = 1);

namespace Sabservis\Api\Http\Response;

use Sabservis\Api\Attribute\OpenApi as OA;

#[OA\Schema]
class ApiErrorDetailResponse extends ApiAbstractResponse
{

	/**
	 * @param array<string, mixed>|null $context
	 */
	public function __construct(
		#[OA\Property(example: 400)]
		public int $code,
		#[OA\Property(example: 'Error message')]
		public string $message,
		#[OA\Property(type: 'object', nullable: true)]
		public array|null $context = null,
	)
	{
	}

}
