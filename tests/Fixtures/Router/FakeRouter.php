<?php declare(strict_types = 1);

namespace Tests\Fixtures\Router;

use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Router\Router;

class FakeRouter implements Router
{

	public function __construct(private bool $match)
	{
	}

	public function match(ApiRequest $request): ApiRequest|null
	{
		if ($this->match) {
			return $request;
		}

		return null;
	}

}
