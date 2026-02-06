<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Patch;
use Sabservis\Api\Attribute\OpenApi\RequestOperationAttribute;

final class PatchTest extends TestCase
{

	#[Test]
	public function returnsPatchHttpMethodAndImplementsRequestOperationContract(): void
	{
		$attribute = new Patch(path: '/users/{id}');

		self::assertInstanceOf(RequestOperationAttribute::class, $attribute);
		self::assertSame('PATCH', $attribute->getMethod());
	}

}
