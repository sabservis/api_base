<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\CookieParameter;

final class CookieParameterTest extends TestCase
{

	#[Test]
	public function setsCookieLocationInSpec(): void
	{
		$parameter = new CookieParameter(
			name: 'session',
			description: 'Session cookie',
		);

		$spec = $parameter->toOpenApiSpec();

		self::assertSame('cookie', $spec['in']);
		self::assertSame('session', $spec['name']);
	}

}
