<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\OperationId\Admin;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\UI\Controller\Controller;

final class UserController implements Controller
{

	/**
	 * @return array<mixed>
	 */
	#[Get(path: '/admin/users')]
	public function list(): array
	{
		return [];
	}

}
