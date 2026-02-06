<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\SecurityScheme;

final class SecuritySchemeTest extends TestCase
{

	#[Test]
	public function toOpenApiSpecReturnsOnlyTypeForMinimalScheme(): void
	{
		$scheme = new SecurityScheme(
			securityScheme: 'apiKeyAuth',
			type: SecurityScheme::TypeApiKey,
		);

		self::assertSame(['type' => SecurityScheme::TypeApiKey], $scheme->toOpenApiSpec());
	}

	#[Test]
	public function toOpenApiSpecIncludesOptionalFieldsWhenProvided(): void
	{
		$scheme = new SecurityScheme(
			securityScheme: 'bearerAuth',
			type: SecurityScheme::TypeHttp,
			description: 'JWT bearer token',
			scheme: 'bearer',
			bearerFormat: 'JWT',
		);

		$result = $scheme->toOpenApiSpec();

		self::assertSame(SecurityScheme::TypeHttp, $result['type']);
		self::assertSame('JWT bearer token', $result['description']);
		self::assertSame('bearer', $result['scheme']);
		self::assertSame('JWT', $result['bearerFormat']);
	}

}
