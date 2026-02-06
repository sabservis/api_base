<?php declare(strict_types = 1);

namespace Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Utils\JsonLimits;

final class JsonLimitsTest extends TestCase
{

	#[Test]
	public function defaultDepthConstantIsSetToExpectedSecurityLimit(): void
	{
		self::assertSame(64, JsonLimits::DEFAULT_DEPTH);
	}

}
