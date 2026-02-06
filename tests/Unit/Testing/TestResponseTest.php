<?php declare(strict_types = 1);

namespace Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Testing\TestResponse;

final class TestResponseTest extends TestCase
{

	#[Test]
	public function headerHelpersDelegateToApiResponse(): void
	{
		$apiResponse = (new ApiResponse())
			->withStatus(200)
			->withHeader('X-Request-Id', 'req-123')
			->withAddedHeader('Set-Cookie', 'a=1')
			->withAddedHeader('Set-Cookie', 'b=2');

		$response = new TestResponse($apiResponse, '{}');

		self::assertSame('req-123', $response->getHeader('x-request-id'));
		self::assertSame(['a=1', 'b=2'], $response->getHeaderValues('set-cookie'));
		self::assertSame('a=1, b=2', $response->getHeaderLine('set-cookie'));
		self::assertSame(['req-123'], $response->getHeaders()['x-request-id']);
	}

	#[Test]
	public function statusShortcutsCoverAllRemainingHelpers(): void
	{
		$cases = [
			['assertNoContent', 204],
			['assertUnauthorized', 401],
			['assertForbidden', 403],
			['assertUnprocessable', 422],
		];

		foreach ($cases as [$method, $status]) {
			$response = new TestResponse((new ApiResponse())->withStatus($status), '{}');
			self::assertSame($response, $response->{$method}());
		}
	}

	#[Test]
	public function getBodyAndJsonReadCapturedOutput(): void
	{
		$response = new TestResponse(new ApiResponse(), '{"id":1,"name":"John"}');

		self::assertSame('{"id":1,"name":"John"}', $response->getBody());
		self::assertSame(['id' => 1, 'name' => 'John'], $response->json());
	}

	#[Test]
	public function jsonReturnsNullForInvalidJson(): void
	{
		$response = new TestResponse(new ApiResponse(), 'not-json');

		self::assertNull($response->json());
	}

	#[Test]
	public function getResponseReturnsOriginalApiResponse(): void
	{
		$apiResponse = (new ApiResponse())->withStatus(201);
		$response = new TestResponse($apiResponse, '{}');

		self::assertSame($apiResponse, $response->getResponse());
	}

}
