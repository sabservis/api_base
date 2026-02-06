<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\OAuthFlowObject;
use Sabservis\Api\OpenApi\Spec\SecuritySchemeObject;

final class SecuritySchemeObjectTest extends TestCase
{

	#[Test]
	public function serializesMinimalSchemeWithTypeOnly(): void
	{
		$scheme = new SecuritySchemeObject(type: SecuritySchemeObject::TypeApiKey);

		self::assertSame(['type' => SecuritySchemeObject::TypeApiKey], $scheme->jsonSerialize());
	}

	#[Test]
	public function serializesOAuthFlowAndOptionalFields(): void
	{
		$scheme = new SecuritySchemeObject(
			type: SecuritySchemeObject::TypeOAuth2,
			description: 'OAuth2 security',
			flows: [
				'authorizationCode' => new OAuthFlowObject(
					scopes: ['read' => 'Read data'],
					authorizationUrl: 'https://example.com/oauth/authorize',
					tokenUrl: 'https://example.com/oauth/token',
				),
			],
		);

		$result = $scheme->jsonSerialize();

		self::assertSame(SecuritySchemeObject::TypeOAuth2, $result['type']);
		self::assertSame('OAuth2 security', $result['description']);
		self::assertArrayHasKey('flows', $result);
	}

}
