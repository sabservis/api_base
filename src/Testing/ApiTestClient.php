<?php declare(strict_types = 1);

namespace Sabservis\Api\Testing;

use Sabservis\Api\Application\ApiApplication;
use Sabservis\Api\Http\ApiRequest;
use function array_merge;
use function json_encode;
use function ob_get_clean;
use function ob_start;
use const JSON_THROW_ON_ERROR;

/**
 * Test client for API testing with fluent interface.
 */
final class ApiTestClient
{

	/** @var array<string, string> */
	private array $defaultHeaders = [];

	public function __construct(private ApiApplication $application)
	{
	}

	/**
	 * Set default headers for all requests.
	 *
	 * @param array<string, string> $headers
	 */
	public function withHeaders(array $headers): self
	{
		$this->defaultHeaders = array_merge($this->defaultHeaders, $headers);

		return $this;
	}

	/**
	 * Set authorization bearer token.
	 */
	public function withToken(string $token): self
	{
		$this->defaultHeaders['authorization'] = 'Bearer ' . $token;

		return $this;
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function get(string $uri, array $headers = []): TestResponse
	{
		return $this->request('GET', $uri, headers: $headers);
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function post(string $uri, string $body = '', array $headers = []): TestResponse
	{
		return $this->request('POST', $uri, body: $body, headers: $headers);
	}

	/**
	 * @param array<mixed> $data
	 * @param array<string, string> $headers
	 */
	public function postJson(string $uri, array $data, array $headers = []): TestResponse
	{
		$headers['content-type'] = 'application/json';

		return $this->request('POST', $uri, body: json_encode($data, JSON_THROW_ON_ERROR), headers: $headers);
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function put(string $uri, string $body = '', array $headers = []): TestResponse
	{
		return $this->request('PUT', $uri, body: $body, headers: $headers);
	}

	/**
	 * @param array<mixed> $data
	 * @param array<string, string> $headers
	 */
	public function putJson(string $uri, array $data, array $headers = []): TestResponse
	{
		$headers['content-type'] = 'application/json';

		return $this->request('PUT', $uri, body: json_encode($data, JSON_THROW_ON_ERROR), headers: $headers);
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function patch(string $uri, string $body = '', array $headers = []): TestResponse
	{
		return $this->request('PATCH', $uri, body: $body, headers: $headers);
	}

	/**
	 * @param array<mixed> $data
	 * @param array<string, string> $headers
	 */
	public function patchJson(string $uri, array $data, array $headers = []): TestResponse
	{
		$headers['content-type'] = 'application/json';

		return $this->request('PATCH', $uri, body: json_encode($data, JSON_THROW_ON_ERROR), headers: $headers);
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function delete(string $uri, array $headers = []): TestResponse
	{
		return $this->request('DELETE', $uri, headers: $headers);
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function request(string $method, string $uri, string $body = '', array $headers = []): TestResponse
	{
		$allHeaders = array_merge($this->defaultHeaders, $headers);

		$request = new ApiRequest(
			method: $method,
			uri: $uri,
			headers: $allHeaders,
			body: $body,
		);

		// Capture output since ApiApplication sends body via echo
		ob_start();
		$response = $this->application->runWith($request);
		$output = ob_get_clean();

		return new TestResponse($response, $output ?: '');
	}

}
