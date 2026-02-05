<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\ClassNameSupport;

final class ClassNameSupportTest extends TestCase
{

	#[Test]
	public function getShortClassNameExtractsLastPart(): void
	{
		$helper = new class {

			use ClassNameSupport;

			public function testGetShortClassName(string $className): string
			{
				return $this->getShortClassName($className);
			}

		};

		self::assertSame('UserDto', $helper->testGetShortClassName('App\\Dto\\UserDto'));
		self::assertSame('Response', $helper->testGetShortClassName('Sabservis\\Api\\Http\\Response'));
		self::assertSame('SimpleClass', $helper->testGetShortClassName('SimpleClass'));
	}

	#[Test]
	public function looksLikeClassNameDetectsNamespace(): void
	{
		$helper = new class {

			use ClassNameSupport;

			public function testLooksLikeClassName(string $value): bool
			{
				return $this->looksLikeClassName($value);
			}

		};

		self::assertTrue($helper->testLooksLikeClassName('App\\Dto\\UserDto'));
		self::assertTrue($helper->testLooksLikeClassName('Sabservis\\Api\\Http\\Response'));
		self::assertFalse($helper->testLooksLikeClassName('SimpleClass'));
		self::assertFalse($helper->testLooksLikeClassName('string'));
		self::assertFalse($helper->testLooksLikeClassName('integer'));
	}

	#[Test]
	public function resolveRefNameReturnsShortNameForClass(): void
	{
		$helper = new class {

			use ClassNameSupport;

			public function testResolveRefName(string|null $ref): string
			{
				return $this->resolveRefName($ref);
			}

		};

		self::assertSame('UserDto', $helper->testResolveRefName('App\\Dto\\UserDto'));
		self::assertSame('Response', $helper->testResolveRefName('Sabservis\\Api\\Http\\Response'));
		self::assertSame('SimpleRef', $helper->testResolveRefName('SimpleRef'));
		self::assertSame('', $helper->testResolveRefName(null));
	}

}
