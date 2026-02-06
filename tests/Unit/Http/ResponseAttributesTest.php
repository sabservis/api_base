<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ResponseAttributes;

final class ResponseAttributesTest extends TestCase
{

	#[Test]
	public function enumContainsObjectAttributeKey(): void
	{
		self::assertSame('api.object', ResponseAttributes::Object->value);
	}

}
