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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

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
	public function pathParameterIsUrlDecoded(): void
	{
		$params = $this->matchNameParameter('/users/john%20doe');

		self::assertSame('john doe', $params['name']);
	}

	#[Test]
	public function pathParameterUtf8IsUrlDecoded(): void
	{
		$params = $this->matchNameParameter('/users/%C5%BElu%C5%A5ou%C4%8Dk%C3%BD');

		self::assertSame('žluťoučký', $params['name']);
	}

	#[Test]
	public function pathParameterKeepsPlusSign(): void
	{
		// RFC 3986: "+" is not a space in path (unlike form-encoded query)
		$params = $this->matchNameParameter('/users/a+b');

		self::assertSame('a+b', $params['name']);
	}

	#[Test]
	public function encodedSlashDoesNotSplitPathSegment(): void
	{
		// Decoded after match - %2F stays inside one segment, value contains "/"
		$params = $this->matchNameParameter('/users/a%2Fb');

		self::assertSame('a/b', $params['name']);
	}

	#[Test]
	public function pathParameterIsDecodedOnlyOnce(): void
	{
		$params = $this->matchNameParameter('/users/100%2525');

		self::assertSame('100%25', $params['name']);
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
				'GET' => ['/users' => 'TestController::testMethod@/users'],
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
	public function recompilesWhenCachedEndpointIsUnknown(): void
	{
		$schema = new Schema();
		$schema->addEndpoint($this->createEndpoint('UserController', 'list', 'GET', '/users'));

		$cache = $this->createMock(CacheInterface::class);
		$cache->method('get')->willReturn([
			// Legacy index-based format / endpoint no longer present
			'staticRoutes' => ['GET' => ['/users' => 0]],
			'dynamicRoutes' => [],
		]);
		$cache->expects($this->once())->method('set')->willReturn(true);

		$matched = (new Router($schema, $cache))->match(new ApiRequest(method: 'GET', uri: 'http://example.com/users'));

		self::assertInstanceOf(ApiRequest::class, $matched);
		$endpoint = $matched->getAttribute(RequestAttributes::Endpoint->value);
		self::assertInstanceOf(Endpoint::class, $endpoint);
		self::assertSame('list', $endpoint->getControllerMethod());
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
	public function cachedRoutesSurviveEndpointReorder(): void
	{
		$cache = new Psr16Cache(new ArrayAdapter());

		// Deploy 1: list() declared before create()
		$schema1 = new Schema();
		$schema1->addEndpoint($this->createEndpoint('UserController', 'list', 'GET', '/users'));
		$schema1->addEndpoint($this->createEndpoint('UserController', 'create', 'POST', '/users'));

		(new Router($schema1, $cache))->match(new ApiRequest(method: 'GET', uri: 'http://example.com/users'));

		// Deploy 2: same routes, methods reordered in controller
		$schema2 = new Schema();
		$schema2->addEndpoint($this->createEndpoint('UserController', 'create', 'POST', '/users'));
		$schema2->addEndpoint($this->createEndpoint('UserController', 'list', 'GET', '/users'));

		$matched = (new Router($schema2, $cache))->match(
			new ApiRequest(method: 'GET', uri: 'http://example.com/users'),
		);

		self::assertInstanceOf(ApiRequest::class, $matched);
		$endpoint = $matched->getAttribute(RequestAttributes::Endpoint->value);
		self::assertInstanceOf(Endpoint::class, $endpoint);
		self::assertSame('list', $endpoint->getControllerMethod());
	}

	#[Test]
	public function cachedRoutesSurviveEndpointMovedToAnotherController(): void
	{
		$cache = new Psr16Cache(new ArrayAdapter());

		$schema1 = new Schema();
		$schema1->addEndpoint($this->createEndpoint('UserController', 'list', 'GET', '/users'));
		$schema1->addEndpoint($this->createEndpoint('OrderController', 'list', 'GET', '/orders'));

		(new Router($schema1, $cache))->match(new ApiRequest(method: 'GET', uri: 'http://example.com/users'));

		// Masks and methods unchanged, but handlers swapped between controllers
		$schema2 = new Schema();
		$schema2->addEndpoint($this->createEndpoint('UserController', 'list', 'GET', '/orders'));
		$schema2->addEndpoint($this->createEndpoint('OrderController', 'list', 'GET', '/users'));

		$matched = (new Router($schema2, $cache))->match(
			new ApiRequest(method: 'GET', uri: 'http://example.com/users'),
		);

		self::assertInstanceOf(ApiRequest::class, $matched);
		$endpoint = $matched->getAttribute(RequestAttributes::Endpoint->value);
		self::assertInstanceOf(Endpoint::class, $endpoint);
		self::assertSame('OrderController', $endpoint->getControllerClass());
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

	/**
	 * @return array<string, mixed>
	 */
	private function matchNameParameter(string $path): array
	{
		$endpoint = $this->createEndpoint('TestController', 'show', 'GET', '/users/{name}');
		$endpoint->setPattern('#^/users/(?P<name>[^/]+)$#');
		$name = new EndpointParameter('name');
		$name->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($name);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$matched = (new Router($schema))->match(new ApiRequest(method: 'GET', uri: 'http://example.com' . $path));
		self::assertInstanceOf(ApiRequest::class, $matched);

		$params = $matched->getAttribute(RequestAttributes::Parameters->value);
		self::assertSame($params['name'], $matched->getAttribute(RequestAttributes::PathParameters->value)['name']);

		return $params;
	}

	private function createEndpoint(string $class, string $method, string $httpMethod, string $mask): Endpoint
	{
		$endpoint = new Endpoint($class, $method);
		$endpoint->addMethod($httpMethod);
		$endpoint->setMask($mask);

		return $endpoint;
	}

}
