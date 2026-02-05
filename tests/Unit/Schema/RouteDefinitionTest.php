<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\RouteDefinition;

final class RouteDefinitionTest extends TestCase
{

	#[Test]
	public function addMethod(): void
	{
		$route = new RouteDefinition();
		$route->addMethod('GET');
		$route->addMethod('post');

		self::assertSame(['GET', 'POST'], $route->getMethods());
	}

	#[Test]
	public function addInvalidMethodThrows(): void
	{
		$route = new RouteDefinition();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Method INVALID is not allowed');

		$route->addMethod('INVALID');
	}

	#[Test]
	public function hasMethod(): void
	{
		$route = new RouteDefinition();
		$route->addMethod('GET');

		self::assertTrue($route->hasMethod('GET'));
		self::assertTrue($route->hasMethod('get'));
		self::assertFalse($route->hasMethod('POST'));
	}

	#[Test]
	public function maskGetterSetter(): void
	{
		$route = new RouteDefinition();
		self::assertNull($route->getMask());

		$route->setMask('/users/{id}');
		self::assertSame('/users/{id}', $route->getMask());
	}

	#[Test]
	public function patternWithRawPattern(): void
	{
		$route = new RouteDefinition();
		$route->setRawPattern('/users/(?P<id>[^/]+)');

		self::assertSame('#^/users/(?P<id>[^/]+)$#', $route->getPattern());
	}

	#[Test]
	public function patternWithoutRawPatternThrows(): void
	{
		$route = new RouteDefinition();

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('Pattern attribute is required');

		$route->getPattern();
	}

	#[Test]
	public function setPatternDirectly(): void
	{
		$route = new RouteDefinition();
		$route->setPattern('#^/custom$#');

		self::assertSame('#^/custom$#', $route->getPattern());
	}

	#[Test]
	public function setMethods(): void
	{
		$route = new RouteDefinition();
		$route->setMethods(['GET', 'POST', 'PUT']);

		self::assertSame(['GET', 'POST', 'PUT'], $route->getMethods());
	}

}
