<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Psr\Log\LoggerInterface;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function sprintf;

#[MiddlewarePriority(10)]
class LoggingMiddleware implements Middleware
{

	public function __construct(private LoggerInterface $logger)
	{
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		$this->logger->info(
			sprintf('Requested url: %s', $request->getUri()),
		);

		return $next($request, $response);
	}

}
