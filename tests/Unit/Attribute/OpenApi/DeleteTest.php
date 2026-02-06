<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Delete;
use Sabservis\Api\Attribute\OpenApi\RequestOperationAttribute;

final class DeleteTest extends TestCase
{

	#[Test]
	public function returnsDeleteHttpMethodAndImplementsRequestOperationContract(): void
	{
		$attribute = new Delete(path: '/users/{id}');

		self::assertInstanceOf(RequestOperationAttribute::class, $attribute);
		self::assertSame('DELETE', $attribute->getMethod());
	}

}
