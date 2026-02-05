<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Schema\EndpointRequestBody;

final class EndpointRequestBodyTest extends TestCase
{

	#[Test]
	public function defaultDescriptionIsNull(): void
	{
		$body = new EndpointRequestBody();

		self::assertNull($body->getDescription());
	}

	#[Test]
	public function defaultEntityIsNull(): void
	{
		$body = new EndpointRequestBody();

		self::assertNull($body->getEntity());
	}

	#[Test]
	public function defaultRequiredIsFalse(): void
	{
		$body = new EndpointRequestBody();

		self::assertFalse($body->isRequired());
	}

	#[Test]
	public function defaultContentSpecIsNull(): void
	{
		$body = new EndpointRequestBody();

		self::assertNull($body->getContentSpec());
	}

	#[Test]
	public function setDescriptionUpdatesValue(): void
	{
		$body = new EndpointRequestBody();
		$body->setDescription('Request body description');

		self::assertSame('Request body description', $body->getDescription());
	}

	#[Test]
	public function setDescriptionToNull(): void
	{
		$body = new EndpointRequestBody();
		$body->setDescription('Some description');
		$body->setDescription(null);

		self::assertNull($body->getDescription());
	}

	#[Test]
	public function setEntityUpdatesValue(): void
	{
		$body = new EndpointRequestBody();
		$body->setEntity('App\\Dto\\CreateUserDto');

		self::assertSame('App\\Dto\\CreateUserDto', $body->getEntity());
	}

	#[Test]
	public function setEntityToNull(): void
	{
		$body = new EndpointRequestBody();
		$body->setEntity('App\\Dto\\SomeDto');
		$body->setEntity(null);

		self::assertNull($body->getEntity());
	}

	#[Test]
	public function setRequiredToTrue(): void
	{
		$body = new EndpointRequestBody();
		$body->setRequired(true);

		self::assertTrue($body->isRequired());
	}

	#[Test]
	public function setRequiredToFalse(): void
	{
		$body = new EndpointRequestBody();
		$body->setRequired(true);
		$body->setRequired(false);

		self::assertFalse($body->isRequired());
	}

	#[Test]
	public function setContentSpecUpdatesValue(): void
	{
		$body = new EndpointRequestBody();
		$spec = [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
			],
		];
		$body->setContentSpec($spec);

		self::assertSame($spec, $body->getContentSpec());
	}

	#[Test]
	public function setContentSpecToNull(): void
	{
		$body = new EndpointRequestBody();
		$body->setContentSpec(['type' => 'string']);
		$body->setContentSpec(null);

		self::assertNull($body->getContentSpec());
	}

}
