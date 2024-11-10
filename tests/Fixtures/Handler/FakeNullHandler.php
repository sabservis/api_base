<?php declare(strict_types = 1);

namespace Tests\Fixtures\Handler;

use Sabservis\Api\Handler\Handler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;

class FakeNullHandler implements Handler
{

	public function handle(ApiRequest $request, ApiResponse $response): mixed
	{
		return null;
	}

}
