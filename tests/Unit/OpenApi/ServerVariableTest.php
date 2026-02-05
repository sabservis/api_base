<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Server;
use Sabservis\Api\Attribute\OpenApi\ServerVariable;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Schema;
use function json_decode;
use function json_encode;

final class ServerVariableTest extends TestCase
{

	#[Test]
	public function serverVariableAttributeToOpenApiSpec(): void
	{
		$variable = new ServerVariable(
			name: 'environment',
			default: 'prod',
			description: 'Server environment',
			enum: ['prod', 'staging', 'dev'],
		);

		$spec = $variable->toOpenApiSpec();

		self::assertSame('prod', $spec['default']);
		self::assertSame('Server environment', $spec['description']);
		self::assertSame(['prod', 'staging', 'dev'], $spec['enum']);
		self::assertSame('environment', $variable->getName());
	}

	#[Test]
	public function serverWithVariablesAttributeToOpenApiSpec(): void
	{
		$server = new Server(
			url: 'https://{environment}.api.example.com',
			description: 'API server',
			variables: [
				new ServerVariable(name: 'environment', default: 'prod', enum: ['prod', 'staging']),
			],
		);

		$spec = $server->toOpenApiSpec();

		self::assertSame('https://{environment}.api.example.com', $spec['url']);
		self::assertSame('API server', $spec['description']);
		self::assertArrayHasKey('variables', $spec);
		self::assertArrayHasKey('environment', $spec['variables']);
		self::assertSame('prod', $spec['variables']['environment']['default']);
		self::assertSame(['prod', 'staging'], $spec['variables']['environment']['enum']);
	}

	#[Test]
	public function serverVariablesInOpenApiConfig(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			servers: [
				[
					'url' => 'https://{environment}.api.example.com/v{version}',
					'description' => 'API server',
					'variables' => [
						'environment' => [
							'default' => 'prod',
							'description' => 'Environment',
							'enum' => ['prod', 'staging', 'dev'],
						],
						'version' => [
							'default' => '1',
							'description' => 'API version',
						],
					],
				],
			],
		);

		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate(new Schema());
		$json = json_encode($spec);
		assert($json !== false);
		$data = json_decode($json, true);

		self::assertCount(1, $data['servers']);
		$server = $data['servers'][0];

		self::assertSame('https://{environment}.api.example.com/v{version}', $server['url']);
		self::assertArrayHasKey('variables', $server);

		// Check environment variable
		self::assertArrayHasKey('environment', $server['variables']);
		self::assertSame('prod', $server['variables']['environment']['default']);
		self::assertSame('Environment', $server['variables']['environment']['description']);
		self::assertSame(['prod', 'staging', 'dev'], $server['variables']['environment']['enum']);

		// Check version variable
		self::assertArrayHasKey('version', $server['variables']);
		self::assertSame('1', $server['variables']['version']['default']);
		self::assertSame('API version', $server['variables']['version']['description']);
	}

	#[Test]
	public function serverWithoutVariablesHasNoVariablesKey(): void
	{
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			servers: [
				['url' => 'https://api.example.com', 'description' => 'Production'],
			],
		);

		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate(new Schema());
		$json = json_encode($spec);
		assert($json !== false);
		$data = json_decode($json, true);

		self::assertArrayNotHasKey('variables', $data['servers'][0]);
	}

}
