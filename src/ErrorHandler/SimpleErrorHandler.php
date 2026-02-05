<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

use Sabservis\Api\Exception\Runtime\SnapshotException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Throwable;
use Tracy\Debugger;

class SimpleErrorHandler implements ErrorHandler
{

	private bool $debugMode = false;

	private ErrorResponseBuilder $responseBuilder;

	public function __construct(ErrorResponseBuilder|null $responseBuilder = null)
	{
		$this->responseBuilder = $responseBuilder ?? new ErrorResponseBuilder();
	}

	public function setDebugMode(bool $debugMode): void
	{
		$this->debugMode = $debugMode;
	}

	public function handle(Throwable $error, ApiRequest $request): ApiResponse
	{
		// Response is inside snapshot, return it
		if ($error instanceof SnapshotException) {
			return $error->getResponse();
		}

		// Log non-debug errors
		if (!$this->debugMode) {
			Debugger::log($error, Debugger::ERROR);
		}

		return $this->responseBuilder->build($error, $this->debugMode);
	}

	public function handleFatal(Throwable $error, ApiRequest $request): ApiResponse
	{
		// Try to log to file - this is a critical failure
		try {
			Debugger::log($error, Debugger::CRITICAL);
		} catch (Throwable) {
			// Ignore logging errors - we must return a response
		}

		return $this->responseBuilder->buildFatal($error, $this->debugMode);
	}

}
