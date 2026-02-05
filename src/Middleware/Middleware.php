<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;

interface Middleware
{

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse;

}
