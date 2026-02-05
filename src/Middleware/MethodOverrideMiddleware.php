<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function in_array;
use function strtoupper;

#[MiddlewarePriority(10)]
class MethodOverrideMiddleware implements Middleware
{

	public const OverrideHeader = 'X-HTTP-Method-Override';

	/**
	 * Allowed HTTP methods that can be used as override.
	 * Only these methods can be set via X-HTTP-Method-Override header.
	 */
	private const AllowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

	/**
	 * @param list<string> $allowedMethods Custom list of allowed override methods (uppercase)
	 */
	public function __construct(
		private readonly array $allowedMethods = self::AllowedMethods,
	)
	{
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		$override = $request->getHeader(self::OverrideHeader);

		if ($override !== null && $override !== '') {
			$method = strtoupper($override);

			if (in_array($method, $this->allowedMethods, true)) {
				$request = $request->withMethod($method);
			}

			// Invalid override method is silently ignored for security
			// (don't reveal which methods are allowed)
		}

		return $next($request, $response);
	}

}
