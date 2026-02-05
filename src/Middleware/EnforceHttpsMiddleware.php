<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;

#[MiddlewarePriority(499)]
class EnforceHttpsMiddleware implements Middleware
{

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		if ($request->getScheme() !== 'https') {
			return $response
				->withStatus(400)
				->writeJsonBody([
					'code' => 400,
					'message' => ErrorMessages::HTTPS_REQUIRED,
				]);
		}

		// Pass to next middleware
		return $next($request, $response);
	}

}
