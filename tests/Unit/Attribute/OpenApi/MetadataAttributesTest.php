<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Contact;
use Sabservis\Api\Attribute\OpenApi\ExternalDocumentation;
use Sabservis\Api\Attribute\OpenApi\Info;
use Sabservis\Api\Attribute\OpenApi\License;

final class MetadataAttributesTest extends TestCase
{

	#[Test]
	public function contactOmitsNullValues(): void
	{
		$contact = new Contact(email: 'support@example.com');

		self::assertSame(['email' => 'support@example.com'], $contact->toOpenApiSpec());
	}

	#[Test]
	public function licenseIncludesUrlAndIdentifierWhenSet(): void
	{
		$license = new License(
			name: 'MIT',
			url: 'https://opensource.org/licenses/MIT',
			identifier: 'MIT',
		);

		self::assertSame([
			'name' => 'MIT',
			'url' => 'https://opensource.org/licenses/MIT',
			'identifier' => 'MIT',
		], $license->toOpenApiSpec());
	}

	#[Test]
	public function infoIncludesNestedContactAndLicense(): void
	{
		$info = new Info(
			title: 'Test API',
			version: '1.2.3',
			contact: new Contact(name: 'Support'),
			license: new License(name: 'MIT'),
		);

		$result = $info->toOpenApiSpec();

		self::assertSame('Test API', $result['title']);
		self::assertSame('1.2.3', $result['version']);
		self::assertSame(['name' => 'Support'], $result['contact']);
		self::assertSame(['name' => 'MIT'], $result['license']);
	}

	#[Test]
	public function externalDocumentationOmitsNullValues(): void
	{
		$docs = new ExternalDocumentation(url: 'https://docs.example.com');

		self::assertSame(['url' => 'https://docs.example.com'], $docs->toOpenApiSpec());
	}

}
