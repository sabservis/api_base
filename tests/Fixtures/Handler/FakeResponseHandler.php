<?php declare(strict_types = 1);

namespace Tests\Fixtures\Handler;

use SabServis\Api\Handler\Handler;
use SabServis\Api\Http\ApiRequest;
use SabServis\Api\Http\ApiResponse;

class FakeResponseHandler implements Handler
{

	public function handle(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		return $response;
	}

}
