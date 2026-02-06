<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\ComponentsObject;
use Sabservis\Api\OpenApi\Spec\InfoObject;
use Sabservis\Api\OpenApi\Spec\OpenApiSpec;
use Sabservis\Api\OpenApi\Spec\OperationObject;
use Sabservis\Api\OpenApi\Spec\PathItemObject;
use Sabservis\Api\OpenApi\Spec\ResponseObject;
use Sabservis\Api\OpenApi\Spec\SecuritySchemeObject;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class OpenApiSpecTest extends TestCase
{

	#[Test]
	public function omitsEmptyComponentsSection(): void
	{
		$spec = new OpenApiSpec(
			info: new InfoObject('Test API', '1.0.0'),
			paths: [
				'/users' => new PathItemObject(
					get: new OperationObject(['200' => new ResponseObject('OK')]),
				),
			],
			components: new ComponentsObject(),
		);

		$result = $spec->jsonSerialize();

		self::assertArrayNotHasKey('components', $result);
	}

	#[Test]
	public function includesNonEmptyComponentsSection(): void
	{
		$spec = new OpenApiSpec(
			info: new InfoObject('Test API', '1.0.0'),
			paths: [
				'/users' => new PathItemObject(
					get: new OperationObject(['200' => new ResponseObject('OK')]),
				),
			],
			components: new ComponentsObject(
				securitySchemes: [
					'bearerAuth' => new SecuritySchemeObject(
						type: SecuritySchemeObject::TypeHttp,
						scheme: 'bearer',
					),
				],
			),
		);

		$result = $spec->jsonSerialize();

		self::assertArrayHasKey('components', $result);
		self::assertArrayHasKey('securitySchemes', $result['components']->jsonSerialize());
	}

	#[Test]
	public function toJsonAndToPrettyJsonProduceValidJson(): void
	{
		$spec = new OpenApiSpec(
			info: new InfoObject('Test API', '1.0.0'),
			paths: [
				'/users' => new PathItemObject(
					get: new OperationObject(['200' => new ResponseObject('OK')]),
				),
			],
		);

		$json = $spec->toJson();
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		$prettyJson = $spec->toPrettyJson();

		self::assertSame('3.0.3', $decoded['openapi']);
		self::assertArrayHasKey('/users', $decoded['paths']);
		self::assertStringContainsString("\n", $prettyJson);
	}

}
