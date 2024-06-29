<?php declare(strict_types = 1);

namespace Sabservis\Api\Dispatcher;

use Sabservis\Api\Decorator\DecoratorManager;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ServerErrorException;
use Sabservis\Api\Exception\Runtime\EarlyReturnResponseException;
use Sabservis\Api\Handler\Handler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Endpoint;
use Throwable;
use function assert;
use function json_encode;
use const JSON_THROW_ON_ERROR;

class JsonDispatcher extends CoreDispatcher
{

	public function __construct(
		protected Router $router,
		protected Handler $handler,
		protected DecoratorManager $decoratorManager,
		protected EntitySerializer $serializer,
		protected EntityValidator|null $validator = null,
	)
	{
		parent::__construct($router, $handler);
	}

	public function fallback(
		ApiRequest $request,
		ApiResponse $response,
	): ApiResponse
	{
		$response = $response->withStatus(404)
			->withHeader('Content-Type', 'application/json');
		$response->getBody()->write(json_encode(['error' => 'No matched route by given URL'], JSON_THROW_ON_ERROR));

		return $response;
	}

	protected function handle(
		ApiRequest $request,
		ApiResponse $response,
	): ApiResponse
	{
		try {
			try {
				$request = $this->decoratorManager->decorateRequest($request, $response);
			} catch (EarlyReturnResponseException $exception) {
				return $exception->getResponse();
			}

			$request = $this->transformRequest($request);
			$result = $this->handler->handle($request, $response);

			// Except ResponseInterface convert all to json
			if ($result instanceof ApiResponse) {
				$response = $result->getObject() !== null
					? $this->transformResponse(
						$result->getObject(),
						$response,
						$result->getStatusCode(),
					)
					: $result;
			} else {
				$response = $this->transformResponse($result, $response);
			}
		} catch (ClientErrorException | ServerErrorException $e) {
			$data = [];

			if ($e->getMessage() !== '') {
				$data['message'] = $e->getMessage();
			}

			if ($e->getCode() !== 0) {
				$data['code'] = $e->getCode();
			}

			if ($e->getContext() !== null) {
				$data['context'] = $e->getContext();
			}

			$response = $response->withStatus($e->getCode() !== 0 ? $e->getCode() : 500)
				->withHeader('Content-Type', 'application/json');
			$response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
		} catch (Throwable $e) {
			throw $e;
		}

		try {
			$response = $this->decoratorManager->decorateResponse($request, $response);
		} catch (EarlyReturnResponseException $exception) {
			return $exception->getResponse();
		}

		return $response;
	}

	/**
	 * Transform incoming request to request DTO, if needed.
	 */
	protected function transformRequest(ApiRequest $request): ApiRequest
	{
		$endpoint = $request->getAttribute(RequestAttributes::Endpoint);
		assert($endpoint instanceof Endpoint);

		$requestBody = $endpoint->getRequestBody();

		if ($requestBody?->getEntity() === null) {
			return $request;
		}

		$dto = $this->serializer->deserialize(
			$request->getContents(),
			$requestBody->getEntity(),
		);

		if ($this->validator !== null) {
			$this->validator->validate($dto);
		}

		return $request->withParsedBody($dto);
	}

	/**
	 * Transform outgoing response data to JSON, if needed.
	 */
	protected function transformResponse(mixed $data, ApiResponse $response, int $statusCode = 200): ApiResponse
	{
		$response = $response->withStatus($statusCode)
			->withHeader('Content-Type', 'application/json');

		$response->getBody()->write($this->serializer->serialize($data));

		return $response;
	}

}
