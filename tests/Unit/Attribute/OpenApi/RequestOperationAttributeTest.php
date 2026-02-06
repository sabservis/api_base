<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\RequestOperationAttribute;

final class RequestOperationAttributeTest extends TestCase
{

	#[Test]
	public function customImplementationExposesMethodAndPath(): void
	{
		$operation = new class implements RequestOperationAttribute
		{

			public function getMethod(): string
			{
				return 'GET';
			}

			public function getPath(): string|null
			{
				return '/health';
			}

		};

		self::assertSame('GET', $operation->getMethod());
		self::assertSame('/health', $operation->getPath());
	}

}
