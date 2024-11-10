<?php declare(strict_types = 1);

namespace Tests\Fixtures\Router;

use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Router\Router;

class FakeRouter implements Router
{

	private bool $match;

	public function __construct(bool $match)
	{
		$this->match = $match;
	}

	public function match(ApiRequest $request): ?ApiRequest
	{
		if ($this->match) {
			return $request;
		}

		return null;
	}

}
