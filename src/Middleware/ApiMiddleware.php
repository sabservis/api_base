<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Throwable;

class ApiMiddleware implements Middleware
{

	public function __construct(protected ApiDispatcher $dispatcher, protected ErrorHandler $errorHandler)
	{
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		try {
			$response = $this->dispatcher->dispatch($request, $response);
		} catch (Throwable $exception) {
			$response = $this->errorHandler->handle($exception, $request);
		}

		return $next($request, $response);
	}

}
