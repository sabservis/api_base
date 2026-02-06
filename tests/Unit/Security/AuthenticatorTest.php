<?php declare(strict_types = 1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Security\Authenticator;

final class AuthenticatorTest extends TestCase
{

	#[Test]
	public function customAuthenticatorImplementationCanReturnIdentity(): void
	{
		$authenticator = new class implements Authenticator
		{

			public function authenticate(ApiRequest $request): mixed
			{
				return ['userId' => 123, 'role' => 'admin'];
			}

		};

		$result = $authenticator->authenticate(new ApiRequest(method: 'GET', uri: '/'));

		self::assertSame(['userId' => 123, 'role' => 'admin'], $result);
	}

}
