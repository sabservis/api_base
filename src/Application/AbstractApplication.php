<?php declare(strict_types = 1);

namespace Sabservis\Api\Application;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sabservis\Api\Middleware\Middleware;
use Throwable;
use function array_merge;
use function call_user_func;
use function call_user_func_array;

abstract class AbstractApplication implements Application
{

	/** @var callable|Middleware */
	private $chain;

	private bool $catchExceptions = false;

	public function __construct(callable|Middleware $chain)
	{
		$this->chain = $chain;
	}

	public function setCatchExceptions(bool $catch = true): void
	{
		$this->catchExceptions = $catch;
	}

	public function run(): ResponseInterface
	{
		$request = $this->createInitialRequest();

		return $this->runWith($request);
	}

	/**
	 * Dispatch application in middleware cycle!
	 */
	public function runWith(ServerRequestInterface $request): ResponseInterface
	{
		$response = $this->createInitialResponse();

		try {

			// Right to the cycle
			$response = call_user_func(
				$this->chain,
				$request,
				$response,
				static fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $response,
			);

			// Response validation check
			if (!isset($response)) {
				throw new RuntimeException('Final response cannot be NULL or unset');
			}
		} catch (Throwable $e) {
			if ($this->catchExceptions !== true) {
				throw $e;
			}
		}

		// Send to finalizer (simple send response)
		return $this->finalize($request, $response);
	}

	abstract protected function createInitialRequest(): ServerRequestInterface;

	abstract protected function createInitialResponse(): ResponseInterface;

	abstract protected function finalize(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface;

	/**
	 * @param array<callable> $handlers
	 * @param array<mixed> $arguments
	 */
	protected function dispatch(array $handlers, array $arguments): mixed
	{
		$ret = null;

		foreach ($handlers as $handler) {
			$ret = call_user_func_array($handler, array_merge($arguments, (array) $ret));
		}

		return $ret;
	}

}
