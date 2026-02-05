<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Throwable;

interface ErrorHandler
{

	/**
	 * Log error and generate response.
	 */
	public function handle(Throwable $error, ApiRequest $request): ApiResponse;

	/**
	 * Emergency fallback when handle() itself fails.
	 * Returns the simplest possible response to avoid infinite loops.
	 */
	public function handleFatal(Throwable $error, ApiRequest $request): ApiResponse;

	public function setDebugMode(bool $debugMode): void;

}
