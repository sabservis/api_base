<?php declare(strict_types = 1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\ErrorMessages;

final class ErrorMessagesTest extends TestCase
{

	#[Test]
	public function exposesCoreErrorMessageTemplates(): void
	{
		self::assertSame('Not found', ErrorMessages::NOT_FOUND);
		self::assertStringContainsString('Unsupported Content-Type', ErrorMessages::UNSUPPORTED_CONTENT_TYPE);
		self::assertStringContainsString('%s', ErrorMessages::METHOD_NOT_ALLOWED);
	}

}
