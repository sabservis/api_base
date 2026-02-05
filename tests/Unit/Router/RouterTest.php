<?php declare(strict_types = 1);

namespace Tests\Unit\Router;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\Schema;

final class RouterTest extends TestCase
{

	#[Test]
	public function matchParameterId(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users/{id}');
		$endpoint->setPattern('#^/users/(?P<id>[^/]+)#');

		$id = new EndpointParameter('id');
		$endpoint->addParameter($id);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/users/22/');
		$request2 = new ApiRequest(method: 'GET', uri: 'http://example.com/not-matched/');
		$router = new Router($schema);
		$matched = $router->match($request);
		$notMatched = $router->match($request2);

		self::assertNull($notMatched);
		self::assertInstanceOf(ApiRequest::class, $matched);
		self::assertArrayHasKey('id', $matched->getAttribute(RequestAttributes::Parameters->value));
		self::assertSame('22', $matched->getAttribute(RequestAttributes::Parameters->value)['id']);
	}

	#[Test]
	public function matchParametersFooBar(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users/{foo}/{bar}');
		$endpoint->setPattern('#^/users/(?P<foo>[^/]+)/(?P<bar>[^/]+)#');

		$foo = new EndpointParameter('foo');
		$endpoint->addParameter($foo);

		$bar = new EndpointParameter('bar');
		$endpoint->addParameter($bar);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/users/1/baz');
		$router = new Router($schema);
		$matched = $router->match($request);

		self::assertInstanceOf(ApiRequest::class, $matched);
		self::assertArrayHasKey('foo', $matched->getAttribute(RequestAttributes::Parameters->value));
		self::assertSame('1', $matched->getAttribute(RequestAttributes::Parameters->value)['foo']);
		self::assertArrayHasKey('bar', $matched->getAttribute(RequestAttributes::Parameters->value));
		self::assertSame('baz', $matched->getAttribute(RequestAttributes::Parameters->value)['bar']);
	}

	#[Test]
	public function matchedSecondEndpointFirstHaveInvalidMethod(): void
	{
		$schema = new Schema();

		$endpoint1 = new Endpoint('TestController', 'method1');
		$endpoint1->addMethod('GET');
		$endpoint1->setMask('/foo');
		$endpoint1->setPattern('#/foo#');
		$schema->addEndpoint($endpoint1);

		$endpoint2 = new Endpoint('TestController', 'method2');
		$endpoint2->addMethod('POST');
		$endpoint2->setMask('/foo');
		$endpoint2->setPattern('#/foo#');
		$schema->addEndpoint($endpoint2);

		$request = new ApiRequest(method: 'POST', uri: 'http://example.com/foo');
		$router = new Router($schema);
		$matched = $router->match($request);

		self::assertSame($endpoint2, $matched->getAttribute(RequestAttributes::Endpoint->value));
	}

	#[Test]
	public function notMatchedInvalidMethod(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setPattern('#/foo#');
		$endpoint->setMask('/foo');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = new ApiRequest(method: 'POST', uri: 'http://example.com/foo');
		$router = new Router($schema);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Method "POST" is not allowed for endpoint "/foo".');

		$router->match($request);
	}

	#[Test]
	public function notMatchedInvalidUrl(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setPattern('#/foo#');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/bar');
		$router = new Router($schema);
		$matched = $router->match($request);

		self::assertNull($matched);
	}

	#[Test]
	public function cachesCompiledRoutes(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$cache = $this->createMock(CacheInterface::class);

		// First call: cache miss, should save to cache
		$cache->expects($this->once())
			->method('get')
			->willReturn(null);

		$cache->expects($this->once())
			->method('set')
			->with(
				$this->stringContains('sabservis_api_router_'),
				$this->isType('array'),
			)
			->willReturn(true);

		$router = new Router($schema, $cache);
		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/users');
		$matched = $router->match($request);

		self::assertInstanceOf(ApiRequest::class, $matched);
	}

	#[Test]
	public function loadsRoutesFromCache(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		// Simulate cached data structure
		$cachedData = [
			'staticRoutes' => [
				'GET' => ['/users' => 0], // index into endpoints array
			],
			'dynamicRoutes' => [],
		];

		$cache = $this->createMock(CacheInterface::class);

		// Cache hit - should NOT call set
		$cache->expects($this->once())
			->method('get')
			->willReturn($cachedData);

		$cache->expects($this->never())
			->method('set');

		$router = new Router($schema, $cache);
		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/users');
		$matched = $router->match($request);

		self::assertInstanceOf(ApiRequest::class, $matched);
		self::assertSame($endpoint, $matched->getAttribute(RequestAttributes::Endpoint->value));
	}

	#[Test]
	public function invalidatesCacheWhenSchemaChanges(): void
	{
		$endpoint1 = new Endpoint('TestController', 'method1');
		$endpoint1->addMethod('GET');
		$endpoint1->setMask('/users');

		$schema1 = new Schema();
		$schema1->addEndpoint($endpoint1);

		$endpoint2 = new Endpoint('TestController', 'method2');
		$endpoint2->addMethod('GET');
		$endpoint2->setMask('/posts'); // Different endpoint

		$schema2 = new Schema();
		$schema2->addEndpoint($endpoint2);

		// Different schemas should produce different cache keys
		self::assertNotSame($schema1->getHash(), $schema2->getHash());
	}

	#[Test]
	public function worksWithoutCache(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		// No cache provided - should work as before
		$router = new Router($schema);
		$request = new ApiRequest(method: 'GET', uri: 'http://example.com/users');
		$matched = $router->match($request);

		self::assertInstanceOf(ApiRequest::class, $matched);
	}

}
