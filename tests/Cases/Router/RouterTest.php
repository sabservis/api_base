<?php declare(strict_types = 1);

namespace Tests\Cases\Router;

require_once __DIR__ . '/../../bootstrap.php';

use QaData\Psr7\Psr7ServerRequestFactory;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Router\SimpleRouter;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointHandler;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\Schema;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class RouterTest extends TestCase
{

	public function testMatchParameterId(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);
		$endpoint->addMethod('GET');
		$endpoint->setPattern('#^/users/(?P<id>[^/]+)#');

		$id = new EndpointParameter('id');
		$endpoint->addParameter($id);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = Psr7ServerRequestFactory::fromGlobals()->withNewUri('http://example.com/users/22/');
		$request = new ApiRequest($request);
		$request2 = $request->withNewUri('http://example.com/not-matched/');
		$router = new SimpleRouter($schema);
		$matched = $router->match($request);
		$notMatched = $router->match($request2);

		Assert::null($notMatched);
		Assert::type($request, $matched);
		Assert::true(isset($matched->getAttribute(RequestAttributes::Parameters)['id']));
		Assert::equal('22', $matched->getAttribute(RequestAttributes::Parameters)['id']);
	}

	public function testMatchParametersFooBar(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);
		$endpoint->addMethod('GET');
		$endpoint->setPattern('#^/users/(?P<foo>[^/]+)/(?P<bar>[^/]+)#');

		$foo = new EndpointParameter('foo');
		$endpoint->addParameter($foo);

		$bar = new EndpointParameter('bar');
		$endpoint->addParameter($bar);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = Psr7ServerRequestFactory::fromGlobals()->withNewUri('http://example.com/users/1/baz');
		$request = new ApiRequest($request);
		$router = new SimpleRouter($schema);
		$matched = $router->match($request);

		Assert::type($request, $matched);
		Assert::true(isset($matched->getAttribute(RequestAttributes::Parameters)['foo']));
		Assert::equal('1', $matched->getAttribute(RequestAttributes::Parameters)['foo']);
		Assert::true(isset($matched->getAttribute(RequestAttributes::Parameters)['bar']));
		Assert::equal('baz', $matched->getAttribute(RequestAttributes::Parameters)['bar']);
	}

	public function testNatchedSecondEndpointFirstHaveInvalidMethod(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$schema = new Schema();

		$endpoint1 = new Endpoint($handler);
		$endpoint1->addMethod('GET');
		$endpoint1->setPattern('#/foo#');
		$schema->addEndpoint($endpoint1);

		$endpoint2 = new Endpoint($handler);
		$endpoint2->addMethod('POST');
		$endpoint2->setPattern('#/foo#');
		$schema->addEndpoint($endpoint2);

		$request = Psr7ServerRequestFactory::fromGlobals()->withNewUri('http://example.com/foo')
			->withMethod('POST');
		$request = new ApiRequest($request);
		$router = new SimpleRouter($schema);
		$matched = $router->match($request);

		Assert::same($matched->getAttribute(RequestAttributes::Endpoint), $endpoint2);
	}

	public function testNotMatchedInvalidMethod(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);
		$endpoint->addMethod('GET');
		$endpoint->setPattern('#/foo#');
		$endpoint->setMask('/foo');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = Psr7ServerRequestFactory::fromGlobals()->withNewUri('http://example.com/foo')
			->withMethod('POST');
		$request = new ApiRequest($request);
		$router = new SimpleRouter($schema);

		Assert::exception(function () use ($router, $request): void {
			$router->match($request);
		}, ClientErrorException::class, 'Method "POST" is not allowed for endpoint "/foo".');
	}

	public function testNotMatchedInvalidUrl(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);
		$endpoint->addMethod('GET');
		$endpoint->setPattern('#/foo#');

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$request = Psr7ServerRequestFactory::fromGlobals()
			->withMethod('GET');
		$request = new ApiRequest($request);
		$router = new SimpleRouter($schema);
		$matched = $router->match($request);

		Assert::null($matched);
	}

}

$test = new RouterTest();
$test->run();

