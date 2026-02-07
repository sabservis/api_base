<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\ListResponse;
use stdClass;
use function fopen;
use function fwrite;
use function rewind;

final class ApiResponseTest extends TestCase
{

	#[Test]
	public function defaultValues(): void
	{
		$response = new ApiResponse();

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('OK', $response->getReasonPhrase());
		self::assertSame('1.1', $response->getProtocolVersion());
		self::assertSame('', $response->getBody());
		self::assertSame([], $response->getHeaders());
	}

	#[Test]
	public function withStatus(): void
	{
		$response = new ApiResponse();
		$newResponse = $response->withStatus(404);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame(404, $newResponse->getStatusCode());
		self::assertSame('Not Found', $newResponse->getReasonPhrase());
	}

	#[Test]
	public function withStatusCustomReason(): void
	{
		$response = new ApiResponse();
		$newResponse = $response->withStatus(404, 'Custom Reason');

		self::assertSame('Custom Reason', $newResponse->getReasonPhrase());
	}

	#[Test]
	public function withProtocolVersion(): void
	{
		$response = new ApiResponse();
		$newResponse = $response->withProtocolVersion('2.0');

		self::assertSame('1.1', $response->getProtocolVersion());
		self::assertSame('2.0', $newResponse->getProtocolVersion());
	}

	#[Test]
	public function headers(): void
	{
		$response = new ApiResponse();

		self::assertFalse($response->hasHeader('content-type'));
		self::assertNull($response->getHeader('content-type'));

		$newResponse = $response->withHeader('Content-Type', 'application/json');

		self::assertFalse($response->hasHeader('content-type'));
		self::assertTrue($newResponse->hasHeader('content-type'));
		self::assertTrue($newResponse->hasHeader('Content-Type'));
		self::assertSame('application/json', $newResponse->getHeader('content-type'));
	}

	#[Test]
	public function withAddedHeader(): void
	{
		$response = new ApiResponse();
		$response = $response->withHeader('Accept', 'text/html');
		$response = $response->withAddedHeader('Accept', 'application/json');

		// Use getHeaderLine() for comma-joined value (PSR-7 compatible)
		self::assertSame('text/html, application/json', $response->getHeaderLine('accept'));
		// getHeader() returns first value for backwards compatibility
		self::assertSame('text/html', $response->getHeader('accept'));
		// getHeaderValues() returns all values as array
		self::assertSame(['text/html', 'application/json'], $response->getHeaderValues('accept'));
	}

	#[Test]
	public function writeBody(): void
	{
		$response = new ApiResponse();
		$response = $response->writeBody('Hello');
		$response = $response->writeBody(' World');

		self::assertSame('Hello World', $response->getBody());
	}

	#[Test]
	public function writeJsonBody(): void
	{
		$response = new ApiResponse();
		$response = $response->writeJsonBody(['name' => 'test']);

		self::assertSame('{"name":"test"}', $response->getBody());
		self::assertSame('application/json', $response->getHeader('content-type'));
	}

	#[Test]
	public function withBody(): void
	{
		$response = new ApiResponse();
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, 'stream content');
		rewind($stream);

		$newResponse = $response->withBody($stream);

		self::assertTrue($newResponse->isStreamBody());
		self::assertIsResource($newResponse->getBody());
	}

	#[Test]
	public function writeBodyOnStreamThrows(): void
	{
		$response = new ApiResponse();
		$stream = fopen('php://temp', 'r+');
		$response = $response->withBody($stream);

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Cannot write to resource body');

		$response->writeBody('test');
	}

	#[Test]
	public function attributes(): void
	{
		$response = new ApiResponse();

		self::assertFalse($response->hasAttribute('test'));
		self::assertSame([], $response->getAttributes());

		$newResponse = $response->withAttribute('test', 'value');

		self::assertFalse($response->hasAttribute('test'));
		self::assertTrue($newResponse->hasAttribute('test'));
		self::assertSame('value', $newResponse->getAttribute('test'));
	}

	#[Test]
	public function getAttributeWithDefault(): void
	{
		$response = new ApiResponse();

		self::assertSame('default', $response->getAttribute('missing', 'default'));
	}

	#[Test]
	public function getAttributeMissingReturnsNull(): void
	{
		$response = new ApiResponse();

		self::assertNull($response->getAttribute('missing'));
	}

	#[Test]
	public function withObject(): void
	{
		$response = new ApiResponse();
		$entity = new stdClass();
		$entity->name = 'test';

		$newResponse = $response->withObject($entity);

		self::assertSame($entity, $newResponse->getObject());
	}

	// === Static factory tests ===

	#[Test]
	public function okFactory(): void
	{
		$entity = new stdClass();
		$entity->name = 'test';

		$response = ApiResponse::ok($entity);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('OK', $response->getReasonPhrase());
		self::assertSame($entity, $response->getObject());
	}

	#[Test]
	public function createdFactory(): void
	{
		$entity = new stdClass();
		$entity->id = 123;

		$response = ApiResponse::created($entity);

		self::assertSame(201, $response->getStatusCode());
		self::assertSame('Created', $response->getReasonPhrase());
		self::assertSame($entity, $response->getObject());
	}

	#[Test]
	public function noContentFactory(): void
	{
		$response = ApiResponse::noContent();

		self::assertSame(204, $response->getStatusCode());
		self::assertSame('No Content', $response->getReasonPhrase());
		self::assertNull($response->getObject());
	}

	#[Test]
	public function listFactoryWithoutMeta(): void
	{
		$data = [['id' => 1], ['id' => 2]];

		$response = ApiResponse::list($data);

		self::assertSame(200, $response->getStatusCode());
		$listResponse = $response->getObject();
		self::assertInstanceOf(ListResponse::class, $listResponse);
		self::assertFalse($listResponse->hasMeta());
		self::assertSame($data, $listResponse->getData());
	}

	#[Test]
	public function listFactoryWithMeta(): void
	{
		$data = [['id' => 1], ['id' => 2]];

		$response = ApiResponse::list($data, total: 100, limit: 20, offset: 0);

		self::assertSame(200, $response->getStatusCode());
		$listResponse = $response->getObject();
		self::assertInstanceOf(ListResponse::class, $listResponse);
		self::assertTrue($listResponse->hasMeta());
		self::assertSame($data, $listResponse->getData());
		self::assertSame(100, $listResponse->getMeta()->total);
		self::assertSame(20, $listResponse->getMeta()->limit);
		self::assertSame(0, $listResponse->getMeta()->offset);
	}

	// === Header Injection Protection Tests ===

	#[Test]
	public function withHeaderSanitizesCRLF(): void
	{
		$response = new ApiResponse();
		// Attempt header injection attack
		$maliciousValue = "text/html\r\nSet-Cookie: admin=true";

		$newResponse = $response->withHeader('Content-Type', $maliciousValue);

		// CRLF should be stripped
		self::assertSame('text/htmlSet-Cookie: admin=true', $newResponse->getHeader('content-type'));
		self::assertStringNotContainsString("\r", $newResponse->getHeader('content-type'));
		self::assertStringNotContainsString("\n", $newResponse->getHeader('content-type'));
	}

	#[Test]
	public function withHeaderSanitizesNullByte(): void
	{
		$response = new ApiResponse();
		$maliciousValue = "text/html\x00malicious";

		$newResponse = $response->withHeader('Content-Type', $maliciousValue);

		self::assertSame('text/htmlmalicious', $newResponse->getHeader('content-type'));
		self::assertStringNotContainsString("\x00", $newResponse->getHeader('content-type'));
	}

	#[Test]
	public function withAddedHeaderSanitizesCRLF(): void
	{
		$response = new ApiResponse();
		$response = $response->withHeader('Accept', 'text/html');
		$maliciousValue = "application/json\r\nX-Injected: true";

		$newResponse = $response->withAddedHeader('Accept', $maliciousValue);

		self::assertStringNotContainsString("\r", $newResponse->getHeader('accept'));
		self::assertStringNotContainsString("\n", $newResponse->getHeader('accept'));
	}

	#[Test]
	public function withHeaderPreservesValidValue(): void
	{
		$response = new ApiResponse();
		$validValue = 'application/json; charset=utf-8';

		$newResponse = $response->withHeader('Content-Type', $validValue);

		self::assertSame($validValue, $newResponse->getHeader('content-type'));
	}

	// === Multi-value Headers Tests ===

	#[Test]
	public function getHeaderValuesReturnsArray(): void
	{
		$response = new ApiResponse();
		$response = $response->withHeader('Accept', 'text/html');

		self::assertSame(['text/html'], $response->getHeaderValues('Accept'));
		self::assertSame(['text/html'], $response->getHeaderValues('accept'));
	}

	#[Test]
	public function getHeaderValuesReturnsEmptyArrayForMissingHeader(): void
	{
		$response = new ApiResponse();

		self::assertSame([], $response->getHeaderValues('Accept'));
	}

	#[Test]
	public function withAddedHeaderCreatesMultipleValues(): void
	{
		$response = new ApiResponse();
		$response = $response
			->withHeader('Set-Cookie', 'session=abc; Path=/')
			->withAddedHeader('Set-Cookie', 'token=xyz; Path=/');

		// Should have two separate values, not comma-joined
		self::assertSame(
			['session=abc; Path=/', 'token=xyz; Path=/'],
			$response->getHeaderValues('set-cookie'),
		);
	}

	#[Test]
	public function getHeaderLineJoinsWithComma(): void
	{
		$response = new ApiResponse();
		$response = $response
			->withHeader('Accept', 'text/html')
			->withAddedHeader('Accept', 'application/json');

		// PSR-7 compatible - join with comma
		self::assertSame('text/html, application/json', $response->getHeaderLine('Accept'));
	}

	#[Test]
	public function getHeaderLineReturnsEmptyStringForMissingHeader(): void
	{
		$response = new ApiResponse();

		self::assertSame('', $response->getHeaderLine('Accept'));
	}

	#[Test]
	public function getHeaderReturnsFirstValue(): void
	{
		$response = new ApiResponse();
		$response = $response
			->withHeader('Set-Cookie', 'first=1')
			->withAddedHeader('Set-Cookie', 'second=2');

		// For backwards compatibility, getHeader returns first value
		self::assertSame('first=1', $response->getHeader('set-cookie'));
	}

	#[Test]
	public function getHeadersReturnsAllValuesAsArrays(): void
	{
		$response = new ApiResponse();
		$response = $response
			->withHeader('Content-Type', 'application/json')
			->withHeader('Set-Cookie', 'a=1')
			->withAddedHeader('Set-Cookie', 'b=2');

		$headers = $response->getHeaders();

		self::assertSame(['application/json'], $headers['content-type']);
		self::assertSame(['a=1', 'b=2'], $headers['set-cookie']);
	}

	#[Test]
	public function withHeaderReplacesAllValues(): void
	{
		$response = new ApiResponse();
		$response = $response
			->withHeader('Accept', 'text/html')
			->withAddedHeader('Accept', 'application/xml')
			->withHeader('Accept', 'application/json'); // Replace all

		self::assertSame(['application/json'], $response->getHeaderValues('accept'));
	}

	#[Test]
	public function withoutHeaderRemovesHeader(): void
	{
		$response = new ApiResponse();
		$response = $response
			->withHeader('Content-Type', 'application/json')
			->withHeader('Accept', 'text/html');

		$newResponse = $response->withoutHeader('Accept');

		self::assertTrue($response->hasHeader('accept'));
		self::assertFalse($newResponse->hasHeader('accept'));
		self::assertTrue($newResponse->hasHeader('content-type'));
	}

	#[Test]
	public function withoutHeaderIsCaseInsensitive(): void
	{
		$response = new ApiResponse();
		$response = $response->withHeader('Content-Type', 'application/json');

		$newResponse = $response->withoutHeader('CONTENT-TYPE');

		self::assertFalse($newResponse->hasHeader('content-type'));
	}

}
