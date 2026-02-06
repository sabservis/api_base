<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\OpenApiAttributeInterface;

final class OpenApiAttributeInterfaceTest extends TestCase
{

	#[Test]
	public function customImplementationReturnsOpenApiSpecArray(): void
	{
		$attribute = new class implements OpenApiAttributeInterface
		{

			public function toOpenApiSpec(): array
			{
				return ['x-test' => true];
			}

		};

		self::assertSame(['x-test' => true], $attribute->toOpenApiSpec());
	}

}
