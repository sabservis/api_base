<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sabservis\Api\Attribute\Core\MiddlewarePriority;

#[MiddlewarePriority(10)]
class MethodOverrideMiddleware implements Middleware
{

	public const OverrideHeader = 'X-HTTP-Method-Override';

	public function __invoke(
		ServerRequestInterface $request,
		ResponseInterface $response,
		callable $next,
	): ResponseInterface
	{
		if ($request->hasHeader(self::OverrideHeader) && $request->getHeader(self::OverrideHeader)[0] !== '') {
			$request = $request->withMethod($request->getHeader(self::OverrideHeader)[0]);
		}

		return $next($request, $response);
	}

}
