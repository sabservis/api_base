<?php declare(strict_types = 1);

namespace Sabservis\Api\Utils;

use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function array_pop;

class ChainBuilder
{

	/** @var array<callable> */
	protected array $middlewares = [];

	public function add(callable $middleware): void
	{
		$this->middlewares[] = $middleware;
	}

	/**
	 * @param array<callable> $middlewares
	 */
	public function addAll(array $middlewares): void
	{
		foreach ($middlewares as $middleware) {
			$this->add($middleware);
		}
	}

	public function create(): callable
	{
		if ($this->middlewares === []) {
			throw new RuntimeStateException('At least one middleware is needed');
		}

		// Terminal handler - just returns response
		$next = static fn (ApiRequest $request, ApiResponse $response): ApiResponse => $response;

		$middlewares = $this->middlewares;

		while ($middleware = array_pop($middlewares)) {
			$currentMiddleware = $middleware;
			$currentNext = $next;
			$next = static fn (ApiRequest $request, ApiResponse $response): ApiResponse
				=> $currentMiddleware($request, $response, $currentNext);
		}

		return $next;
	}

}
