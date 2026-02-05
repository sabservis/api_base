<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Schema\EndpointResponse;
use Sabservis\Api\Schema\OpenApiDefinition;

final class OpenApiDefinitionTest extends TestCase
{

	#[Test]
	public function addResponse(): void
	{
		$openApi = new OpenApiDefinition();

		$response = new EndpointResponse('200', 'OK');
		$openApi->addResponse($response);

		self::assertTrue($openApi->hasResponse('200'));
		self::assertFalse($openApi->hasResponse('404'));
		self::assertCount(1, $openApi->getResponses());
	}

	#[Test]
	public function setResponses(): void
	{
		$openApi = new OpenApiDefinition();

		$response1 = new EndpointResponse('200', 'OK');
		$response2 = new EndpointResponse('404', 'Not Found');

		$openApi->setResponses([$response1, $response2]);

		self::assertCount(2, $openApi->getResponses());
		self::assertTrue($openApi->hasResponse('200'));
		self::assertTrue($openApi->hasResponse('404'));
	}

	#[Test]
	public function tags(): void
	{
		$openApi = new OpenApiDefinition();

		self::assertSame([], $openApi->getTags());
		self::assertFalse($openApi->hasTag('users'));
		self::assertNull($openApi->getTag('users'));

		$openApi->addTag('users', 'User management');

		self::assertTrue($openApi->hasTag('users'));
		self::assertSame('User management', $openApi->getTag('users'));
		self::assertSame(['users' => 'User management'], $openApi->getTags());
	}

	#[Test]
	public function setTags(): void
	{
		$openApi = new OpenApiDefinition();

		$openApi->setTags(['users' => 1, 'admin' => 1]);

		self::assertTrue($openApi->hasTag('users'));
		self::assertTrue($openApi->hasTag('admin'));
	}

	#[Test]
	public function openApiSpec(): void
	{
		$openApi = new OpenApiDefinition();

		self::assertSame([], $openApi->getOpenApi());

		$spec = ['x-custom' => 'value', 'security' => [['bearerAuth' => []]]];
		$openApi->setOpenApi($spec);

		self::assertSame($spec, $openApi->getOpenApi());
	}

	#[Test]
	public function addTagWithNullValue(): void
	{
		$openApi = new OpenApiDefinition();

		$openApi->addTag('users');

		self::assertTrue($openApi->hasTag('users'));
		self::assertNull($openApi->getTag('users'));
	}

}
