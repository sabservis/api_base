<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\HandlerDefinition;

final class HandlerDefinitionTest extends TestCase
{

	#[Test]
	public function constructorSetsControllerInfo(): void
	{
		$handler = new HandlerDefinition('App\\Controller\\UserController', 'getUser');

		self::assertSame('App\\Controller\\UserController', $handler->getControllerClass());
		self::assertSame('getUser', $handler->getControllerMethod());
	}

	#[Test]
	public function addParameter(): void
	{
		$handler = new HandlerDefinition('TestController', 'test');

		$param = new EndpointParameter('id', 'int');
		$handler->addParameter($param);

		self::assertTrue($handler->hasParameter('id'));
		self::assertFalse($handler->hasParameter('name'));
		self::assertCount(1, $handler->getParameters());
	}

	#[Test]
	public function getParametersByIn(): void
	{
		$handler = new HandlerDefinition('TestController', 'test');

		$pathParam = new EndpointParameter('id', 'int');
		$pathParam->setIn(EndpointParameter::InPath);
		$handler->addParameter($pathParam);

		$queryParam = new EndpointParameter('limit', 'int');
		$queryParam->setIn(EndpointParameter::InQuery);
		$handler->addParameter($queryParam);

		$pathParams = $handler->getParametersByIn(EndpointParameter::InPath);
		$queryParams = $handler->getParametersByIn(EndpointParameter::InQuery);

		self::assertCount(1, $pathParams);
		self::assertCount(1, $queryParams);
		self::assertSame('id', array_values($pathParams)[0]->getName());
		self::assertSame('limit', array_values($queryParams)[0]->getName());
	}

	#[Test]
	public function setParameters(): void
	{
		$handler = new HandlerDefinition('TestController', 'test');

		$param1 = new EndpointParameter('id', 'int');
		$param2 = new EndpointParameter('name', 'string');

		$handler->setParameters([$param1, $param2]);

		self::assertCount(2, $handler->getParameters());
		self::assertTrue($handler->hasParameter('id'));
		self::assertTrue($handler->hasParameter('name'));
	}

	#[Test]
	public function requestBody(): void
	{
		$handler = new HandlerDefinition('TestController', 'test');
		self::assertNull($handler->getRequestBody());

		$requestBody = new EndpointRequestBody();
		$requestBody->setEntity('App\\DTO\\CreateUserDto');
		$handler->setRequestBody($requestBody);

		self::assertSame($requestBody, $handler->getRequestBody());
	}

}
