<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\HeaderParameter;

final class HeaderParameterTest extends TestCase
{

	#[Test]
	public function setsHeaderLocationInSpec(): void
	{
		$parameter = new HeaderParameter(
			name: 'X-Request-Id',
			description: 'Trace identifier',
		);

		$spec = $parameter->toOpenApiSpec();

		self::assertSame('header', $spec['in']);
		self::assertSame('X-Request-Id', $spec['name']);
	}

}
