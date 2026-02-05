<?php declare(strict_types = 1);

namespace Sabservis\Api\ErrorHandler;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\ApiException;
use Sabservis\Api\Exception\Runtime\SnapshotException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Throwable;

class PsrLogErrorHandler extends SimpleErrorHandler
{

	public function __construct(
		private LoggerInterface $logger,
		ErrorResponseBuilder|null $responseBuilder = null,
	)
	{
		parent::__construct($responseBuilder);
	}

	public function handle(Throwable $error, ApiRequest $request): ApiResponse
	{
		$logError = $error;

		if ($logError instanceof SnapshotException) {
			$logError = $logError->getPrevious();
		}

		// Log exception only if it's not designed to be displayed
		if ($logError !== null && !$logError instanceof ApiException) {
			$this->logger->error($logError->getMessage(), ['exception' => $logError]);
		}

		// Also log original exception if any
		if ($logError instanceof ApiException && ($previous = $logError->getPrevious()) !== null) {
			// Server error is expected to contain a real error while client error can contain just information, why client request failed
			$level = $logError instanceof ServerErrorException ? LogLevel::ERROR : LogLevel::DEBUG;
			$this->logger->log($level, $previous->getMessage(), ['exception' => $previous]);
		}

		return parent::handle($error, $request);
	}

	public function handleFatal(Throwable $error, ApiRequest $request): ApiResponse
	{
		// Log as critical - this means the error handler itself failed
		$this->logger->critical('Fatal error in error handler: ' . $error->getMessage(), [
			'exception' => $error,
		]);

		return parent::handleFatal($error, $request);
	}

}
