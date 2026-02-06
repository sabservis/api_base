<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Head;
use Sabservis\Api\Attribute\OpenApi\RequestOperationAttribute;

final class HeadTest extends TestCase
{

	#[Test]
	public function returnsHeadHttpMethodAndImplementsRequestOperationContract(): void
	{
		$attribute = new Head(path: '/status');

		self::assertInstanceOf(RequestOperationAttribute::class, $attribute);
		self::assertSame('HEAD', $attribute->getMethod());
	}

}
