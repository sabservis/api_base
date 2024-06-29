<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Sabservis\Api\Dispatcher\DispatchError;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\ApiException;
use Sabservis\Api\Exception\Runtime\SnapshotException;
use Sabservis\Api\Http\ApiResponse;
use Throwable;
use Tracy\Debugger;
use function json_encode;
use const JSON_THROW_ON_ERROR;

class SimpleErrorHandler implements ErrorHandler
{

	private bool $debugMode = false;

	public function setDebugMode(bool $debugMode): void
	{
		$this->debugMode = $debugMode;
	}

	public function handle(DispatchError $dispatchError): ApiResponse
	{
		$error = $dispatchError->getError();

		// Response is inside snapshot, return it
		if ($error instanceof SnapshotException) {
			return $error->getResponse();
		}

		// No response available, create new from error
		return $this->createResponseFromError($error);
	}

	protected function createResponseFromError(Throwable $error): ApiResponse
	{
		$code = $error instanceof ApiException ? $error->getCode() : 500;

		if ($this->debugMode === true) {
			$data = [
				'code' => $error->getCode(),
				'context' => $error->getTrace(),
				'message' => $error->getMessage(),
			];
		} else {

			// Lof the error to the file
			Debugger::log($error, Debugger::ERROR);

			$data = [
				'code' => $error instanceof ApiException ? $error->getCode() : 500,
				'context' => [],
				'message' => $error instanceof ApiException ? $error->getMessage() : ServerErrorException::$defaultMessage,
			];
		}

		if ($error instanceof ApiException && ($context = $error->getContext()) !== null) {
			$data['context'] = $context;
		}

		$body = Utils::streamFor(json_encode($data, JSON_THROW_ON_ERROR));

		$response = new ApiResponse(new Response());

		return $response
			->withStatus($code)
			->withHeader('Content-Type', 'application/json')
			->withBody($body);
	}

}
