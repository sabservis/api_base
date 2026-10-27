<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\OperationId;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\UI\Controller\Controller;

final class PingController implements Controller
{

	/**
	 * @return array<mixed>
	 */
	#[Get(path: '/ping')]
	#[Post(path: '/ping')]
	public function check(): array
	{
		return [];
	}

	/**
	 * @return array<mixed>
	 */
	#[Get(path: '/status')]
	public function status(): array
	{
		return [];
	}

	/**
	 * @return array<mixed>
	 */
	#[Get(path: '/legacy/users', operationId: 'userList')]
	public function legacyUsers(): array
	{
		return [];
	}

}
