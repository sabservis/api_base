<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use function assert;

#[MiddlewarePriority(200)]
class CORSMiddleware implements Middleware
{

	private function decorate(ResponseInterface $response): ResponseInterface
	{
		return $response
			->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Access-Control-Allow-Methods', '*')
			->withHeader('Access-Control-Allow-Headers', '*');
	}

	public function __invoke(
		ServerRequestInterface $request,
		ResponseInterface $response,
		callable $next,
	): ResponseInterface
	{
		// Preflight request
		if ($request->getMethod() === 'OPTIONS') {
			return $this->decorate($response);
		}

		$response = $next($request, $response);
		assert($response instanceof ResponseInterface);

		return $this->decorate($response);
	}

}
