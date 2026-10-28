<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Generator\OperationIdResolver;

final class OperationIdResolverTest extends TestCase
{

	#[Test]
	public function aliasNextToNamespaceCollisionKeepsPrefixAndGetsNumber(): void
	{
		$ids = OperationIdResolver::resolve([
			['class' => 'App\Admin\UserController', 'httpMethod' => 'GET', 'id' => 'userList', 'explicit' => false],
			['class' => 'App\Front\UserController', 'httpMethod' => 'GET', 'id' => 'userList', 'explicit' => false],
			['class' => 'App\Front\UserController', 'httpMethod' => 'GET', 'id' => 'userList', 'explicit' => false],
		]);

		self::assertSame(['adminUserList', 'frontUserList', 'frontUserList2'], $ids);
	}

	#[Test]
	public function aliasNumberSkipsTakenIds(): void
	{
		$ids = OperationIdResolver::resolve([
			['class' => 'App\UserController', 'httpMethod' => 'GET', 'id' => 'userList', 'explicit' => false],
			['class' => 'App\UserController', 'httpMethod' => 'GET', 'id' => 'userList', 'explicit' => false],
			['class' => 'App\OtherController', 'httpMethod' => 'GET', 'id' => 'userList2', 'explicit' => true],
		]);

		self::assertSame(['userList', 'userList3', 'userList2'], $ids);
	}

	#[Test]
	public function aliasesAreNumberedByPathNotByAttributeOrder(): void
	{
		$ids = OperationIdResolver::resolve([
			['class' => 'App\\ContactController', 'httpMethod' => 'GET', 'id' => 'contactList', 'explicit' => false, 'path' => '/contacts'],
			['class' => 'App\\ContactController', 'httpMethod' => 'GET', 'id' => 'contactList', 'explicit' => false, 'path' => '/zeta'],
			['class' => 'App\\ContactController', 'httpMethod' => 'GET', 'id' => 'contactList', 'explicit' => false, 'path' => '/alpha'],
		]);

		// The endpoint's own path (registered first) keeps the plain id
		self::assertSame(['contactList', 'contactList3', 'contactList2'], $ids);
	}

}
