<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\ContactObject;
use Sabservis\Api\OpenApi\Spec\DiscriminatorObject;
use Sabservis\Api\OpenApi\Spec\ExternalDocsObject;
use Sabservis\Api\OpenApi\Spec\HeaderObject;
use Sabservis\Api\OpenApi\Spec\InfoObject;
use Sabservis\Api\OpenApi\Spec\LicenseObject;
use Sabservis\Api\OpenApi\Spec\LinkObject;
use Sabservis\Api\OpenApi\Spec\MediaTypeObject;
use Sabservis\Api\OpenApi\Spec\OAuthFlowObject;
use Sabservis\Api\OpenApi\Spec\RequestBodyObject;
use Sabservis\Api\OpenApi\Spec\ResponseObject;
use Sabservis\Api\OpenApi\Spec\SchemaObject;
use Sabservis\Api\OpenApi\Spec\ServerObject;
use Sabservis\Api\OpenApi\Spec\ServerVariableObject;
use Sabservis\Api\OpenApi\Spec\TagObject;

final class OpenApiSupportObjectsTest extends TestCase
{

	#[Test]
	public function infoIncludesNestedContactAndLicense(): void
	{
		$info = new InfoObject(
			title: 'API',
			version: '1.0.0',
			contact: new ContactObject(
				name: 'Support',
				email: 'support@example.com',
			),
			license: new LicenseObject(
				name: 'MIT',
				url: 'https://opensource.org/licenses/MIT',
			),
		);

		$result = $info->jsonSerialize();

		self::assertSame('API', $result['title']);
		self::assertSame('Support', $result['contact']->jsonSerialize()['name']);
		self::assertSame('MIT', $result['license']->jsonSerialize()['name']);
	}

	#[Test]
	public function tagAndExternalDocsSerializeAsExpected(): void
	{
		$tag = new TagObject(
			name: 'users',
			description: 'User operations',
			externalDocs: new ExternalDocsObject(
				url: 'https://docs.example.com/users',
				description: 'Detailed docs',
			),
		);

		$result = $tag->jsonSerialize();

		self::assertSame('users', $result['name']);
		self::assertSame('User operations', $result['description']);
		self::assertSame(
			'https://docs.example.com/users',
			$result['externalDocs']->jsonSerialize()['url'],
		);
	}

	#[Test]
	public function mediaTypeRequestBodyAndResponseSerializeAsExpected(): void
	{
		$mediaType = new MediaTypeObject(
			schema: new SchemaObject(type: 'object'),
			example: ['name' => 'John'],
		);
		$requestBody = new RequestBodyObject(
			content: ['application/json' => $mediaType],
			required: true,
		);
		$response = new ResponseObject(
			description: 'Success',
			content: ['application/json' => $mediaType],
		);

		self::assertArrayHasKey('schema', $mediaType->jsonSerialize());
		self::assertTrue($requestBody->jsonSerialize()['required']);
		self::assertSame('Success', $response->jsonSerialize()['description']);
	}

	#[Test]
	public function headerDiscriminatorAndServerVariableSerializeAsExpected(): void
	{
		$header = new HeaderObject(
			required: true,
			schema: new SchemaObject(type: 'string'),
		);
		$discriminator = new DiscriminatorObject(
			propertyName: 'type',
			mapping: ['cat' => '#/components/schemas/Cat'],
		);
		$serverVariable = new ServerVariableObject(
			default: 'prod',
			enum: ['prod', 'staging'],
		);

		self::assertTrue($header->jsonSerialize()['required']);
		self::assertSame('type', $discriminator->jsonSerialize()['propertyName']);
		self::assertSame(['prod', 'staging'], $serverVariable->jsonSerialize()['enum']);
	}

	#[Test]
	public function linkAndServerSerializeWithNestedObjects(): void
	{
		$server = new ServerObject(
			url: 'https://{env}.example.com',
			variables: [
				'env' => new ServerVariableObject(default: 'prod', enum: ['prod']),
			],
		);
		$link = new LinkObject(operationId: 'getUser', server: $server);
		$oauthFlow = new OAuthFlowObject(
			scopes: ['read' => 'Read access'],
			tokenUrl: 'https://example.com/oauth/token',
		);

		self::assertSame('getUser', $link->jsonSerialize()['operationId']);
		self::assertArrayHasKey('variables', $server->jsonSerialize());
		self::assertArrayHasKey('scopes', $oauthFlow->jsonSerialize());
	}

}
