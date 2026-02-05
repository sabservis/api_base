<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Utils\Regex;

final class EndpointTest extends TestCase
{

	#[Test]
	public function addMethodSuccess(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$endpoint->addMethod('GET');
		$endpoint->addMethod('POST');

		self::assertSame(['GET', 'POST'], $endpoint->getMethods());
	}

	#[Test]
	public function addMethodFailLowercase(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Method FOO is not allowed');

		$endpoint->addMethod('foo');
	}

	#[Test]
	public function addMethodFailUppercase(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Method FOO is not allowed');

		$endpoint->addMethod('FOO');
	}

	#[Test]
	public function hasMethodFail(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		self::assertFalse($endpoint->hasMethod('foo'));
	}

	#[Test]
	public function getPatternFail(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('Pattern attribute is required');

		$endpoint->getPattern();
	}

	#[Test]
	public function getPatternWithoutVariables(): void
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
			$endpoint = new Endpoint('TestController', 'testMethod');

			$endpoint->getRouteDefinition()->setRawPattern($parameters['rawPattern']);

			self::assertSame([$parameters['uri']], Regex::match($parameters['uri'], $endpoint->getPattern()));
		}
	}

	#[Test]
	public function getPatternWithVariables(): void
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
			$endpoint = new Endpoint('TestController', 'testMethod');

			$endpoint->getRouteDefinition()->setRawPattern($parameters['rawPattern']);

			self::assertSame(
				[$parameters['uri'], 'id' => '1', '1'],
				Regex::match($parameters['uri'], $endpoint->getPattern()),
			);
		}
	}

	#[Test]
	public function getParametersByInEmpty(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		self::assertSame([], $endpoint->getParametersByIn('foo'));
	}

	#[Test]
	public function getParametersByInCookies(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$p1 = new EndpointParameter('p1');
		$p1->setIn(EndpointParameter::InCookie);
		$endpoint->addParameter($p1);

		$p2 = new EndpointParameter('p2');
		$p2->setIn(EndpointParameter::InCookie);
		$endpoint->addParameter($p2);

		$p3 = new EndpointParameter('p3');
		$p3->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($p3);

		self::assertSame(['p1' => $p1, 'p2' => $p2], $endpoint->getParametersByIn(EndpointParameter::InCookie));
	}

	#[Test]
	public function tag(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$endpoint->addTag('t1');

		self::assertTrue($endpoint->hasTag('t1'));
	}

	#[Test]
	public function controllerClassAndMethod(): void
	{
		$endpoint = new Endpoint('App\\Controller\\UserController', 'getUsers');

		self::assertSame('App\\Controller\\UserController', $endpoint->getControllerClass());
		self::assertSame('getUsers', $endpoint->getControllerMethod());
	}

}
