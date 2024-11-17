<?php declare(strict_types = 1);

namespace Tests\Cases\Schema;

require_once __DIR__ . '/../../bootstrap.php';

use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointHandler;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Utils\Regex;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class EndpointTest extends TestCase
{

	public function testAddMethodSuccess(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		$endpoint->addMethod($endpoint::METHOD_GET);
		$endpoint->addMethod($endpoint::METHOD_POST);

		Assert::same([$endpoint::METHOD_GET, $endpoint::METHOD_POST], $endpoint->getMethods());
	}

	public function testAddMethodFail(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		Assert::exception(static function () use ($endpoint): void {
			$endpoint->addMethod('foo');
		}, InvalidArgumentException::class, 'Method FOO is not allowed');

		Assert::exception(static function () use ($endpoint): void {
			$endpoint->addMethod('FOO');
		}, InvalidArgumentException::class, 'Method FOO is not allowed');
	}

	public function testHasMethodFail(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		Assert::false($endpoint->hasMethod('foo'));
	}

	public function testGetPatternFail(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		Assert::exception(static function () use ($endpoint): void {
			$endpoint->getPattern();
		}, InvalidStateException::class, 'Pattern attribute is required');
	}

	public function testGetPatternWithoutVariables(): void
	{
		$data = [
			[
				'rawPattern' => '/path/to/users',
				'uri' => '/path/to/users',
			],
			[
				'rawPattern' => '/path/to/users\.something',
				'uri' => '/path/to/users.something',
			],
		];

		foreach ($data as $parameters) {
			$handler = new EndpointHandler('class', 'method');
			$endpoint = new Endpoint($handler);

			$endpoint->setAttribute('pattern', $parameters['rawPattern']);

			Assert::same([$parameters['uri']], Regex::match($parameters['uri'], $endpoint->getPattern()));
		}
	}

	public function testGetPatternWithVariables(): void
	{
		$data = [
			[
				'rawPattern' => '/path/to/users/(?P<id>[^/]+)',
				'uri' => '/path/to/users/1',
			],
			[
				'rawPattern' => '/path/to/users\.something/(?P<id>[^/]+)',
				'uri' => '/path/to/users.something/1',
			],
		];

		foreach ($data as $parameters) {
			$handler = new EndpointHandler('class', 'method');
			$endpoint = new Endpoint($handler);

			$endpoint->setAttribute('pattern', $parameters['rawPattern']);

			// Test regex matches uri path and find parameter
			Assert::same(
				[$parameters['uri'], 'id' => '1', '1'],
				Regex::match($parameters['uri'], $endpoint->getPattern()),
			);
		}
	}

	public function testGetParametersByInEmpty(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		Assert::same([], $endpoint->getParametersByIn('foo'));
	}

	public function testGetParametersByInCookies(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		$p1 = new EndpointParameter('p1');
		$p1->setIn(EndpointParameter::InCookie);
		$endpoint->addParameter($p1);

		$p2 = new EndpointParameter('p2');
		$p2->setIn(EndpointParameter::InCookie);
		$endpoint->addParameter($p2);

		$p3 = new EndpointParameter('p3');
		$p3->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($p3);

		Assert::same(['p1' => $p1, 'p2' => $p2], $endpoint->getParametersByIn(EndpointParameter::InCookie));
	}

	public function testTag(): void
	{
		$handler = new EndpointHandler('class', 'method');

		$endpoint = new Endpoint($handler);

		$endpoint->addTag('t1');

		Assert::true($endpoint->hasTag('t1'));
	}

}

$test = new EndpointTest();
$test->run();
