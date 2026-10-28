<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\OperationId;

use Sabservis\Api\Attribute\OpenApi\Alias;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\UI\Controller\Controller;

final class ContactController implements Controller
{

	/**
	 * @return array<mixed>
	 */
	#[Get(path: '/contacts')]
	#[Alias('/people')]
	#[Alias('/persons')]
	public function list(): array
	{
		return [];
	}

}
