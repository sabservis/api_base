<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Loader\ParsedOperation;

final class ParsedOperationTest extends TestCase
{

	#[Test]
	public function defaultsContainSecurityInheritanceMarker(): void
	{
		$parsed = new ParsedOperation();

		self::assertSame([], $parsed->httpMethods);
		self::assertSame('', $parsed->path);
		self::assertSame(ParsedOperation::SECURITY_INHERIT, $parsed->security);
	}

	#[Test]
	public function constructorStoresProvidedValues(): void
	{
		$parsed = new ParsedOperation(
			httpMethods: ['GET'],
			path: '/users',
			id: 'listUsers',
			deprecated: true,
			tags: ['users' => true],
			security: [['bearerAuth' => []]],
		);

		self::assertSame(['GET'], $parsed->httpMethods);
		self::assertSame('/users', $parsed->path);
		self::assertSame('listUsers', $parsed->id);
		self::assertTrue($parsed->deprecated);
		self::assertSame([['bearerAuth' => []]], $parsed->security);
	}

}
