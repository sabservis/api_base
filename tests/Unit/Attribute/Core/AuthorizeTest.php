<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\Core;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\Core\Authorize;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Security\Authorizer;

final class AuthorizeTest extends TestCase
{

	#[Test]
	public function storesActivityAndAuthorizer(): void
	{
		$attribute = new Authorize(
			activity: 'invoice.read',
			authorizer: TestAuthorizeAttributeAuthorizer::class,
		);

		self::assertSame('invoice.read', $attribute->activity);
		self::assertSame(TestAuthorizeAttributeAuthorizer::class, $attribute->authorizer);
	}

}

final class TestAuthorizeAttributeAuthorizer implements Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return true;
	}

}
