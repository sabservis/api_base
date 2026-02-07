<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use ArrayObject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\RuntimeStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\Schema\Endpoint;
use stdClass;
use function fclose;
use function fopen;
use function fwrite;
use function rewind;
use function str_repeat;
use function strlen;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

final class ApiRequestTest extends TestCase
{

	#[Test]
	public function parameters(): void
	{
		$apiRequest = new ApiRequest(method: 'GET', uri: 'http://example.com/');

		self::assertFalse($apiRequest->hasParameter('name'));
		self::assertFalse($apiRequest->hasParameter('fake'));
		self::assertNull($apiRequest->getParameter('name'));
		self::assertSame('default', $apiRequest->getParameter('name', 'default'));
		self::assertSame([], $apiRequest->getParameters());
	}

	#[Test]
	public function parametersWithAttribute(): void
	{
		$apiRequest = new ApiRequest(method: 'GET', uri: 'http://example.com/');
		$apiRequest = $apiRequest->withAttribute(
			RequestAttributes::Parameters->value,
			['name' => 'John Doe', 'title' => null],
		);

		self::assertTrue($apiRequest->hasParameter('name'));
		self::assertTrue($apiRequest->hasParameter('title'));
		self::assertFalse($apiRequest->hasParameter('fake'));
		self::assertSame('John Doe', $apiRequest->getParameter('name'));
		self::assertSame(['name' => 'John Doe', 'title' => null], $apiRequest->getParameters());
		self::assertFalse($apiRequest->hasParameter('company'));
		self::assertNull($apiRequest->getParameter('company'));
		self::assertSame('default', $apiRequest->getParameter('company', 'default'));
	}

	#[Test]
	public function fromGlobals(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test/path?foo=bar';
		$_GET['foo'] = 'bar';

		$request = ApiRequest::fromGlobals();

		self::assertSame('POST', $request->getMethod());
		self::assertSame('/test/path?foo=bar', $request->getRawUri());
		self::assertSame('/test/path', $request->getPath());
		self::assertSame(['foo' => 'bar'], $request->getQueryParams());
	}

	#[Test]
	public function withMethod(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');
		$newRequest = $request->withMethod('POST');

		self::assertSame('GET', $request->getMethod());
		self::assertSame('POST', $newRequest->getMethod());
	}

	#[Test]
	public function headers(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['content-type' => 'application/json', 'x-custom' => 'value'],
		);

		self::assertTrue($request->hasHeader('content-type'));
		self::assertTrue($request->hasHeader('Content-Type'));
		self::assertSame('application/json', $request->getHeader('content-type'));
		self::assertSame('application/json', $request->getHeader('Content-Type'));
		self::assertNull($request->getHeader('non-existent'));

		$newRequest = $request->withHeader('X-New', 'new-value');
		self::assertFalse($request->hasHeader('x-new'));
		self::assertTrue($newRequest->hasHeader('x-new'));
		self::assertSame('new-value', $newRequest->getHeader('x-new'));
	}

	#[Test]
	public function cookies(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			cookies: ['session' => 'abc123'],
		);

		self::assertSame(['session' => 'abc123'], $request->getCookies());
		self::assertSame('abc123', $request->getCookie('session'));
		self::assertNull($request->getCookie('non-existent'));
		self::assertSame('default', $request->getCookie('non-existent', 'default'));
	}

	#[Test]
	public function queryParams(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			queryParams: ['page' => '1', 'limit' => '10'],
		);

		self::assertSame(['page' => '1', 'limit' => '10'], $request->getQueryParams());
		self::assertSame('1', $request->getQueryParam('page'));
		self::assertNull($request->getQueryParam('non-existent'));
		self::assertSame('default', $request->getQueryParam('non-existent', 'default'));
	}

	#[Test]
	public function body(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '{"name":"test"}',
		);

		self::assertSame('{"name":"test"}', $request->getContents());
	}

	#[Test]
	public function parsedBody(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/');

		self::assertNull($request->getParsedBody());

		$data = ['name' => 'test'];
		$newRequest = $request->withParsedBody($data);

		self::assertNull($request->getParsedBody());
		self::assertSame($data, $newRequest->getParsedBody());
	}

	#[Test]
	public function scheme(): void
	{
		$httpRequest = new ApiRequest(method: 'GET', uri: '/', serverParams: []);
		self::assertSame('http', $httpRequest->getScheme());
		self::assertFalse($httpRequest->isSecured());

		$httpsRequest = new ApiRequest(method: 'GET', uri: '/', serverParams: ['HTTPS' => 'on']);
		self::assertSame('https', $httpsRequest->getScheme());
		self::assertTrue($httpsRequest->isSecured());
	}

	#[Test]
	public function isSecuredDetectsForwardedProtoHeader(): void
	{
		// Behind reverse proxy with X-Forwarded-Proto: https
		// Note: Trusted proxy must be configured for header to be trusted
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'], // Proxy IP
		);

		// Configure trusted proxy - now header is trusted
		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		self::assertTrue($request->isSecured());
		self::assertSame('https', $request->getScheme());
	}

	#[Test]
	public function isSecuredReturnsFalseForForwardedProtoHttp(): void
	{
		// X-Forwarded-Proto explicitly set to http (from trusted proxy)
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'http'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		self::assertFalse($request->isSecured());
		self::assertSame('http', $request->getScheme());
	}

	#[Test]
	public function isSecuredPrefersServerHttpsOverForwardedProto(): void
	{
		// Direct HTTPS takes precedence (edge case: misconfigured proxy)
		// Note: HTTPS server param is always trusted, even without trusted proxies
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'http'],
			serverParams: ['HTTPS' => 'on', 'REMOTE_ADDR' => '1.2.3.4'],
		);

		self::assertTrue($request->isSecured());
	}

	#[Test]
	public function requestAttributesEnumValues(): void
	{
		// Test that enum cases have correct string values
		self::assertSame('api.endpoint', RequestAttributes::Endpoint->value);
		self::assertSame('api.router', RequestAttributes::Router->value);
		self::assertSame('api.parameters', RequestAttributes::Parameters->value);
		self::assertSame('api.request.entity', RequestAttributes::RequestEntity->value);
	}

	#[Test]
	public function getEndpointReturnsNullWhenNotSet(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');

		self::assertNull($request->getEndpoint());
	}

	#[Test]
	public function getEndpointReturnsEndpoint(): void
	{
		$endpoint = new Endpoint('App\\Controller', 'index');
		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);

		self::assertSame($endpoint, $request->getEndpoint());
	}

	#[Test]
	public function getRouteParamsReturnsEmptyArrayWhenNotSet(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');

		self::assertSame([], $request->getRouteParams());
	}

	#[Test]
	public function getRouteParamsReturnsRouterAttribute(): void
	{
		$params = ['id' => '123', 'slug' => 'test'];
		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request->withAttribute(RequestAttributes::Router->value, $params);

		self::assertSame($params, $request->getRouteParams());
	}

	#[Test]
	public function getTypedEntityReturnsTypedObject(): void
	{
		$entity = new stdClass();
		$entity->name = 'test';

		$request = new ApiRequest(method: 'POST', uri: '/');
		$request = $request->withAttribute(RequestAttributes::RequestEntity->value, $entity);

		$result = $request->getTypedEntity(stdClass::class);

		self::assertInstanceOf(stdClass::class, $result);
		self::assertSame('test', $result->name);
	}

	#[Test]
	public function getTypedEntityThrowsOnTypeMismatch(): void
	{
		$entity = new stdClass();

		$request = new ApiRequest(method: 'POST', uri: '/');
		$request = $request->withAttribute(RequestAttributes::RequestEntity->value, $entity);

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('Expected entity of type ArrayObject, got stdClass');

		$request->getTypedEntity(ArrayObject::class);
	}

	#[Test]
	public function getTypedEntityThrowsWhenNoEntity(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/');

		$this->expectException(RuntimeStateException::class);
		$this->expectExceptionMessage('No request entity found');

		$request->getTypedEntity(stdClass::class);
	}

	// === Uploaded Files Tests ===

	#[Test]
	public function noUploadedFiles(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/');

		self::assertFalse($request->hasUploadedFile('avatar'));
		self::assertNull($request->getUploadedFile('avatar'));
		self::assertSame([], $request->getUploadedFiles('avatar'));
		self::assertSame([], $request->getAllUploadedFiles());
		self::assertSame([], $request->getUploadedFileNames());
	}

	#[Test]
	public function singleUploadedFile(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'avatar' => [
					'name' => 'photo.jpg',
					'type' => 'image/jpeg',
					'size' => 12_345,
					'tmp_name' => '/tmp/phpABC123',
					'error' => UPLOAD_ERR_OK,
				],
			],
		);

		self::assertTrue($request->hasUploadedFile('avatar'));
		self::assertFalse($request->hasUploadedFile('document'));

		$file = $request->getUploadedFile('avatar');
		self::assertInstanceOf(UploadedFile::class, $file);
		self::assertSame('photo.jpg', $file->getName());
		self::assertSame('image/jpeg', $file->getContentType());
		self::assertSame(12_345, $file->getSize());

		// getUploadedFiles returns array even for single file
		$files = $request->getUploadedFiles('avatar');
		self::assertCount(1, $files);
		self::assertInstanceOf(UploadedFile::class, $files[0]);

		self::assertSame(['avatar'], $request->getUploadedFileNames());
	}

	#[Test]
	public function multipleUploadedFiles(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'documents' => [
					'name' => ['doc1.pdf', 'doc2.pdf', 'doc3.pdf'],
					'type' => ['application/pdf', 'application/pdf', 'application/pdf'],
					'size' => [100, 200, 300],
					'tmp_name' => ['/tmp/php1', '/tmp/php2', '/tmp/php3'],
					'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK],
				],
			],
		);

		self::assertTrue($request->hasUploadedFile('documents'));

		// getUploadedFile returns null for multiple files
		self::assertNull($request->getUploadedFile('documents'));

		$files = $request->getUploadedFiles('documents');
		self::assertCount(3, $files);

		self::assertSame('doc1.pdf', $files[0]->getName());
		self::assertSame('doc2.pdf', $files[1]->getName());
		self::assertSame('doc3.pdf', $files[2]->getName());

		self::assertSame(100, $files[0]->getSize());
		self::assertSame(200, $files[1]->getSize());
		self::assertSame(300, $files[2]->getSize());
	}

	#[Test]
	public function mixedSingleAndMultipleFiles(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'avatar' => [
					'name' => 'photo.jpg',
					'type' => 'image/jpeg',
					'size' => 5_000,
					'tmp_name' => '/tmp/phpAvatar',
					'error' => UPLOAD_ERR_OK,
				],
				'documents' => [
					'name' => ['a.pdf', 'b.pdf'],
					'type' => ['application/pdf', 'application/pdf'],
					'size' => [100, 200],
					'tmp_name' => ['/tmp/php1', '/tmp/php2'],
					'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
				],
			],
		);

		self::assertSame(['avatar', 'documents'], $request->getUploadedFileNames());

		$avatar = $request->getUploadedFile('avatar');
		self::assertInstanceOf(UploadedFile::class, $avatar);
		self::assertSame('photo.jpg', $avatar->getName());

		$documents = $request->getUploadedFiles('documents');
		self::assertCount(2, $documents);
	}

	#[Test]
	public function uploadedFilesSkipsNoFileErrors(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'optional' => [
					'name' => '',
					'type' => '',
					'size' => 0,
					'tmp_name' => '',
					'error' => UPLOAD_ERR_NO_FILE,
				],
			],
		);

		// File with UPLOAD_ERR_NO_FILE should be ignored
		self::assertFalse($request->hasUploadedFile('optional'));
		self::assertNull($request->getUploadedFile('optional'));
		self::assertSame([], $request->getAllUploadedFiles());
	}

	#[Test]
	public function multipleFilesSkipsNoFileErrors(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'documents' => [
					'name' => ['doc1.pdf', '', 'doc3.pdf'],
					'type' => ['application/pdf', '', 'application/pdf'],
					'size' => [100, 0, 300],
					'tmp_name' => ['/tmp/php1', '', '/tmp/php3'],
					'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
				],
			],
		);

		$files = $request->getUploadedFiles('documents');

		// Only 2 files should be present (middle one was empty)
		self::assertCount(2, $files);
		self::assertSame('doc1.pdf', $files[0]->getName());
		self::assertSame('doc3.pdf', $files[1]->getName());
	}

	#[Test]
	public function fileWithUploadErrorIsIncludedInCollection(): void
	{
		// File with error (not NO_FILE) should still be in collection
		// so user can check isOk() and get error message
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'document' => [
					'name' => 'large_file.pdf',
					'type' => 'application/pdf',
					'size' => 0,
					'tmp_name' => '',
					'error' => UPLOAD_ERR_INI_SIZE,
				],
			],
		);

		self::assertTrue($request->hasUploadedFile('document'));

		$file = $request->getUploadedFile('document');
		self::assertInstanceOf(UploadedFile::class, $file);
		self::assertFalse($file->isOk());
		self::assertSame(UPLOAD_ERR_INI_SIZE, $file->getError());
	}

	#[Test]
	public function invalidFilesStructureIsIgnored(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'invalid1' => 'not an array',
				'invalid2' => ['missing' => 'error key'],
				'invalid3' => [],
				'valid' => [
					'name' => 'test.txt',
					'type' => 'text/plain',
					'size' => 100,
					'tmp_name' => '/tmp/php123',
					'error' => UPLOAD_ERR_OK,
				],
			],
		);

		// Invalid entries should be silently ignored
		self::assertFalse($request->hasUploadedFile('invalid1'));
		self::assertFalse($request->hasUploadedFile('invalid2'));
		self::assertFalse($request->hasUploadedFile('invalid3'));

		// Valid entry should work
		self::assertTrue($request->hasUploadedFile('valid'));
		self::assertSame(['valid'], $request->getUploadedFileNames());
	}

	#[Test]
	public function emptyMultipleFilesArray(): void
	{
		// When user submits form with multiple file input but no files selected
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'documents' => [
					'name' => [],
					'type' => [],
					'size' => [],
					'tmp_name' => [],
					'error' => [],
				],
			],
		);

		// Should result in empty array, not error
		self::assertTrue($request->hasUploadedFile('documents'));
		self::assertSame([], $request->getUploadedFiles('documents'));
	}

	#[Test]
	public function multipleFilesWithMixedErrors(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			files: [
				'documents' => [
					'name' => ['ok.pdf', 'too_large.pdf', 'partial.pdf'],
					'type' => ['application/pdf', 'application/pdf', 'application/pdf'],
					'size' => [100, 0, 50],
					'tmp_name' => ['/tmp/php1', '', '/tmp/php3'],
					'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL],
				],
			],
		);

		$files = $request->getUploadedFiles('documents');

		// All 3 files should be present (only NO_FILE is skipped)
		self::assertCount(3, $files);

		self::assertTrue($files[0]->isOk());
		self::assertFalse($files[1]->isOk());
		self::assertFalse($files[2]->isOk());

		self::assertSame(UPLOAD_ERR_INI_SIZE, $files[1]->getError());
		self::assertSame(UPLOAD_ERR_PARTIAL, $files[2]->getError());
	}

	// =====================
	// Entity tests
	// =====================

	#[Test]
	public function hasEntityReturnsFalseWhenNoEntity(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/');

		self::assertFalse($request->hasEntity());
	}

	#[Test]
	public function hasEntityReturnsTrueWhenEntitySet(): void
	{
		$entity = new stdClass();
		$request = (new ApiRequest(method: 'POST', uri: '/'))
			->withAttribute(RequestAttributes::RequestEntity->value, $entity);

		self::assertTrue($request->hasEntity());
	}

	#[Test]
	public function getEntityReturnsNullWhenNoEntity(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/');

		self::assertNull($request->getEntity());
	}

	#[Test]
	public function getEntityReturnsDefaultWhenNoEntity(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/');
		$default = new stdClass();

		self::assertSame($default, $request->getEntity($default));
	}

	#[Test]
	public function getEntityReturnsEntityWhenSet(): void
	{
		$entity = new stdClass();
		$entity->name = 'test';

		$request = (new ApiRequest(method: 'POST', uri: '/'))
			->withAttribute(RequestAttributes::RequestEntity->value, $entity);

		self::assertSame($entity, $request->getEntity());
	}

	// =====================
	// JSON Body tests
	// =====================

	#[Test]
	public function getJsonBodyParsesValidJson(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '{"name":"John","age":30}',
		);

		$result = $request->getJsonBody();

		self::assertSame(['name' => 'John', 'age' => 30], $result);
	}

	#[Test]
	public function getJsonBodyReturnsNullForEmptyBody(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/', body: '');

		self::assertNull($request->getJsonBody());
	}

	#[Test]
	public function getJsonBodyReturnsNullForInvalidJson(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '{invalid json}',
		);

		self::assertNull($request->getJsonBody());
	}

	#[Test]
	public function getJsonBodyReturnsNullForNonArrayJson(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '"just a string"',
		);

		self::assertNull($request->getJsonBody());
	}

	#[Test]
	public function getJsonBodyRejectsDeepNestedJson(): void
	{
		// Create JSON nested deeper than the 64-level limit
		$depth = 70;
		$json = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

		$request = new ApiRequest(method: 'POST', uri: '/', body: $json);

		// Should return null (depth exceeded causes json_decode to fail)
		self::assertNull($request->getJsonBody());
	}

	#[Test]
	public function getJsonBodyAcceptsWithinDepthLimit(): void
	{
		// Create JSON nested within the 64-level limit
		$depth = 60;
		$json = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

		$request = new ApiRequest(method: 'POST', uri: '/', body: $json);

		$result = $request->getJsonBody();

		self::assertIsArray($result);
	}

	// =====================
	// getJsonBodyOrFail tests
	// =====================

	#[Test]
	public function getJsonBodyOrFailReturnsArrayForValidJson(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '{"name":"John","age":30}',
		);

		$result = $request->getJsonBodyOrFail();

		self::assertSame(['name' => 'John', 'age' => 30], $result);
	}

	#[Test]
	public function getJsonBodyOrFailThrowsForEmptyBody(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/', body: '');

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage('Request body is empty');

		$request->getJsonBodyOrFail();
	}

	#[Test]
	public function getJsonBodyOrFailThrowsForInvalidJson(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '{invalid json}',
		);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage('Invalid JSON');

		$request->getJsonBodyOrFail();
	}

	#[Test]
	public function getJsonBodyOrFailIncludesJsonErrorMessage(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '{"unclosed": ',
		);

		try {
			$request->getJsonBodyOrFail();
			self::fail('Expected ClientErrorException');
		} catch (ClientErrorException $e) {
			// Should contain the actual JSON error
			self::assertStringContainsString('Invalid JSON:', $e->getMessage());
			self::assertSame(400, $e->getCode());
		}
	}

	#[Test]
	public function getJsonBodyOrFailThrowsForNonArrayJson(): void
	{
		$request = new ApiRequest(
			method: 'POST',
			uri: '/',
			body: '"just a string"',
		);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage('JSON must be an object or array');

		$request->getJsonBodyOrFail();
	}

	#[Test]
	public function getJsonBodyOrFailThrowsForJsonNumber(): void
	{
		$request = new ApiRequest(method: 'POST', uri: '/', body: '42');

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);

		$request->getJsonBodyOrFail();
	}

	#[Test]
	public function getJsonBodyOrFailThrowsForTooDeepJson(): void
	{
		// Create JSON nested deeper than the 64-level limit
		$depth = 70;
		$json = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

		$request = new ApiRequest(method: 'POST', uri: '/', body: $json);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage('Invalid JSON');

		$request->getJsonBodyOrFail();
	}

	// =====================
	// Trusted Proxies tests
	// =====================

	#[Test]
	public function isSecuredIgnoresForwardedProtoWithoutTrustedProxies(): void
	{
		// Without trusted proxies configured, X-Forwarded-Proto should be ignored
		// This prevents attackers from spoofing HTTPS
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '1.2.3.4'],
		);

		// Without trusted proxies, should NOT trust the header
		self::assertFalse($request->isSecured());
		self::assertSame('http', $request->getScheme());
	}

	#[Test]
	public function isSecuredTrustsForwardedProtoFromTrustedProxy(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		// With trusted proxy configured, should trust the header
		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		self::assertTrue($request->isSecured());
		self::assertSame('https', $request->getScheme());
	}

	#[Test]
	public function isSecuredIgnoresForwardedProtoFromUntrustedIp(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '1.2.3.4'], // Not in trusted range
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Request is not from trusted proxy, should ignore header
		self::assertFalse($request->isSecured());
	}

	#[Test]
	public function isSecuredWithExactIpMatch(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '192.168.1.100'],
		);

		$request = $request->withTrustedProxies(['192.168.1.100']);

		self::assertTrue($request->isSecured());
	}

	#[Test]
	public function isSecuredWithMultipleTrustedProxies(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-Proto' => 'https'],
			serverParams: ['REMOTE_ADDR' => '172.16.0.5'],
		);

		$request = $request->withTrustedProxies([
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
		]);

		self::assertTrue($request->isSecured());
	}

	#[Test]
	public function directHttpsAlwaysWorksWithoutTrustedProxies(): void
	{
		// Direct HTTPS (not behind proxy) should always work
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['HTTPS' => 'on', 'REMOTE_ADDR' => '1.2.3.4'],
		);

		self::assertTrue($request->isSecured());
	}

	#[Test]
	public function withTrustedProxiesReturnsNewInstance(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');
		$newRequest = $request->withTrustedProxies(['10.0.0.0/8']);

		self::assertNotSame($request, $newRequest);
	}

	#[Test]
	public function getTrustedProxiesReturnsEmptyArrayByDefault(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');

		self::assertSame([], $request->getTrustedProxies());
	}

	#[Test]
	public function getTrustedProxiesReturnsConfiguredProxies(): void
	{
		$proxies = ['10.0.0.0/8', '172.16.0.0/12'];
		$request = (new ApiRequest(method: 'GET', uri: '/'))
			->withTrustedProxies($proxies);

		self::assertSame($proxies, $request->getTrustedProxies());
	}

	// =====================
	// getClientIp tests
	// =====================

	#[Test]
	public function getClientIpReturnsRemoteAddrByDefault(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			serverParams: ['REMOTE_ADDR' => '1.2.3.4'],
		);

		self::assertSame('1.2.3.4', $request->getClientIp());
	}

	#[Test]
	public function getClientIpReturnsUnknownWhenNoRemoteAddr(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');

		self::assertSame('unknown', $request->getClientIp());
	}

	#[Test]
	public function getClientIpIgnoresForwardedForWithoutTrustedProxies(): void
	{
		// Attacker tries to spoof IP via X-Forwarded-For
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '10.0.0.1'],
			serverParams: ['REMOTE_ADDR' => '1.2.3.4'],
		);

		// Without trusted proxies, should return REMOTE_ADDR
		self::assertSame('1.2.3.4', $request->getClientIp());
	}

	#[Test]
	public function getClientIpParsesForwardedForFromTrustedProxy(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '203.0.113.50'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'], // Trusted proxy
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Should return the client IP from X-Forwarded-For
		self::assertSame('203.0.113.50', $request->getClientIp());
	}

	#[Test]
	public function getClientIpParsesMultipleForwardedForIps(): void
	{
		// X-Forwarded-For: client, proxy1, proxy2
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '203.0.113.50, 10.0.0.5, 10.0.0.6'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Should return the leftmost non-trusted IP (the actual client)
		self::assertSame('203.0.113.50', $request->getClientIp());
	}

	#[Test]
	public function getClientIpIgnoresForwardedForFromUntrustedProxy(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '10.0.0.1'],
			serverParams: ['REMOTE_ADDR' => '1.2.3.4'], // Not in trusted range
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Request is not from trusted proxy, should ignore header
		self::assertSame('1.2.3.4', $request->getClientIp());
	}

	#[Test]
	public function getClientIpHandlesEmptyForwardedFor(): void
	{
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => ''],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Empty header, should fall back to REMOTE_ADDR
		self::assertSame('10.0.0.1', $request->getClientIp());
	}

	#[Test]
	public function getClientIpSkipsTrustedProxiesInChain(): void
	{
		// Scenario: client -> proxy1 (trusted) -> proxy2 (trusted) -> server
		// X-Forwarded-For: 203.0.113.50, 10.0.0.5
		// REMOTE_ADDR: 10.0.0.1
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '203.0.113.50, 10.0.0.5'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Should skip 10.0.0.5 (trusted) and return 203.0.113.50 (client)
		self::assertSame('203.0.113.50', $request->getClientIp());
	}

	#[Test]
	public function getClientIpReturnsLeftmostIpWhenAllAreTrusted(): void
	{
		// Edge case: all IPs in chain are trusted (internal request)
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => '10.0.0.50, 10.0.0.5'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// All IPs are trusted, return leftmost
		self::assertSame('10.0.0.50', $request->getClientIp());
	}

	#[Test]
	public function getClientIpValidatesExtractedIp(): void
	{
		// Malformed X-Forwarded-For with invalid IP
		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['X-Forwarded-For' => 'not-an-ip, garbage'],
			serverParams: ['REMOTE_ADDR' => '10.0.0.1'],
		);

		$request = $request->withTrustedProxies(['10.0.0.0/8']);

		// Invalid IP, should fall back to REMOTE_ADDR
		self::assertSame('10.0.0.1', $request->getClientIp());
	}

	// =====================
	// Body size limit tests
	// =====================

	#[Test]
	public function readBodyWithLimitReadsWithinLimit(): void
	{
		$content = 'Hello World';
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$result = ApiRequest::readBodyWithLimit($stream, 1_000);

		self::assertSame($content, $result);

		fclose($stream);
	}

	#[Test]
	public function readBodyWithLimitThrowsWhenExceedingLimit(): void
	{
		$content = str_repeat('x', 1_000);
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(413);

		try {
			ApiRequest::readBodyWithLimit($stream, 500);
		} finally {
			fclose($stream);
		}
	}

	#[Test]
	public function readBodyWithLimitReadsExactlyAtLimit(): void
	{
		$content = str_repeat('x', 500);
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$result = ApiRequest::readBodyWithLimit($stream, 500);

		self::assertSame($content, $result);

		fclose($stream);
	}

	#[Test]
	public function readBodyWithLimitHandlesEmptyStream(): void
	{
		$stream = fopen('php://temp', 'r+');

		$result = ApiRequest::readBodyWithLimit($stream, 1_000);

		self::assertSame('', $result);

		fclose($stream);
	}

	#[Test]
	public function readBodyWithLimitReadsInChunks(): void
	{
		// Create content larger than default chunk size (8KB) but within limit
		$content = str_repeat('a', 20_000);
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$result = ApiRequest::readBodyWithLimit($stream, 50_000);

		self::assertSame($content, $result);
		self::assertSame(20_000, strlen($result));

		fclose($stream);
	}

	// ================================
	// Content-Length validation tests
	// ================================

	#[Test]
	public function fromGlobalsWithLimitRejectsNegativeContentLength(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test';
		$_SERVER['CONTENT_LENGTH'] = '-1';

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid Content-Length header');
		$this->expectExceptionCode(400);

		ApiRequest::fromGlobalsWithLimit(10_000);
	}

	#[Test]
	public function fromGlobalsWithLimitRejectsNonNumericContentLength(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test';
		$_SERVER['CONTENT_LENGTH'] = 'abc';

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid Content-Length header');
		$this->expectExceptionCode(400);

		ApiRequest::fromGlobalsWithLimit(10_000);
	}

	#[Test]
	public function fromGlobalsWithLimitAcceptsValidContentLength(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test';
		$_SERVER['CONTENT_LENGTH'] = '100';

		// This should not throw since 100 < 10_000
		$request = ApiRequest::fromGlobalsWithLimit(10_000);

		self::assertSame('POST', $request->getMethod());
	}

	#[Test]
	public function fromGlobalsWithLimitAcceptsZeroContentLength(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/test';
		$_SERVER['CONTENT_LENGTH'] = '0';

		// Zero is valid (empty body)
		$request = ApiRequest::fromGlobalsWithLimit(10_000);

		self::assertSame('POST', $request->getMethod());
		self::assertSame('', $request->getContents());
	}

}
