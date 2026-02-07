<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\Schema;
use function strlen;

final class SchemaTest extends TestCase
{

	#[Test]
	public function emptySchemaHasNoEndpoints(): void
	{
		$schema = new Schema();

		self::assertSame([], $schema->getEndpoints());
	}

	#[Test]
	public function addEndpointAddsToCollection(): void
	{
		$schema = new Schema();
		$endpoint = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');

		$schema->addEndpoint($endpoint);

		self::assertCount(1, $schema->getEndpoints());
		self::assertSame($endpoint, $schema->getEndpoints()[0]);
	}

	#[Test]
	public function addMultipleEndpoints(): void
	{
		$schema = new Schema();

		$endpoint1 = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint1->addMethod('GET');
		$endpoint1->setMask('/users');

		$endpoint2 = new Endpoint('App\\Controller\\UserController', 'create');
		$endpoint2->addMethod('POST');
		$endpoint2->setMask('/users');

		$schema->addEndpoint($endpoint1);
		$schema->addEndpoint($endpoint2);

		self::assertCount(2, $schema->getEndpoints());
	}

	#[Test]
	public function emptySchemaHasNoTags(): void
	{
		$schema = new Schema();

		self::assertSame([], $schema->getTags());
	}

	#[Test]
	public function addTagWithDescription(): void
	{
		$schema = new Schema();
		$schema->addTag('users', 'User management');

		$tags = $schema->getTags();

		self::assertArrayHasKey('users', $tags);
		self::assertSame('users', $tags['users']['name']);
		self::assertSame('User management', $tags['users']['description']);
	}

	#[Test]
	public function addTagWithoutDescription(): void
	{
		$schema = new Schema();
		$schema->addTag('auth');

		$tags = $schema->getTags();

		self::assertArrayHasKey('auth', $tags);
		self::assertNull($tags['auth']['description']);
	}

	#[Test]
	public function duplicateTagIsNotAdded(): void
	{
		$schema = new Schema();
		$schema->addTag('users', 'First description');
		$schema->addTag('users', 'Second description');

		$tags = $schema->getTags();

		self::assertCount(1, $tags);
		self::assertSame('First description', $tags['users']['description']);
	}

	#[Test]
	public function tagWithDescriptionOverwritesTagWithoutDescription(): void
	{
		$schema = new Schema();
		$schema->addTag('users');
		$schema->addTag('users', 'User management');

		$tags = $schema->getTags();

		self::assertSame('User management', $tags['users']['description']);
	}

	#[Test]
	public function tagWithoutDescriptionDoesNotOverwriteExisting(): void
	{
		$schema = new Schema();
		$schema->addTag('users', 'User management');
		$schema->addTag('users');

		$tags = $schema->getTags();

		self::assertSame('User management', $tags['users']['description']);
	}

	#[Test]
	public function getHashReturnsConsistentValue(): void
	{
		$schema = new Schema();
		$endpoint = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');
		$schema->addEndpoint($endpoint);

		$hash1 = $schema->getHash();
		$hash2 = $schema->getHash();

		self::assertSame($hash1, $hash2);
	}

	#[Test]
	public function getHashIsMd5Format(): void
	{
		$schema = new Schema();
		$endpoint = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');
		$schema->addEndpoint($endpoint);

		$hash = $schema->getHash();

		self::assertSame(32, strlen($hash));
		self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
	}

	#[Test]
	public function differentEndpointsProduceDifferentHashes(): void
	{
		$schema1 = new Schema();
		$endpoint1 = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint1->addMethod('GET');
		$endpoint1->setMask('/users');
		$schema1->addEndpoint($endpoint1);

		$schema2 = new Schema();
		$endpoint2 = new Endpoint('App\\Controller\\OrderController', 'list');
		$endpoint2->addMethod('GET');
		$endpoint2->setMask('/orders');
		$schema2->addEndpoint($endpoint2);

		self::assertNotSame($schema1->getHash(), $schema2->getHash());
	}

	#[Test]
	public function sameEndpointsProduceSameHash(): void
	{
		$schema1 = new Schema();
		$endpoint1 = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint1->addMethod('GET');
		$endpoint1->setMask('/users');
		$schema1->addEndpoint($endpoint1);

		$schema2 = new Schema();
		$endpoint2 = new Endpoint('App\\Controller\\DifferentController', 'different');
		$endpoint2->addMethod('GET');
		$endpoint2->setMask('/users');
		$schema2->addEndpoint($endpoint2);

		// Hash is based on mask and methods, not controller
		self::assertSame($schema1->getHash(), $schema2->getHash());
	}

	#[Test]
	public function emptySchemaHasHash(): void
	{
		$schema = new Schema();

		$hash = $schema->getHash();

		self::assertSame(32, strlen($hash));
	}

}
