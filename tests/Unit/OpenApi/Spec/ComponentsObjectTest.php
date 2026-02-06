<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\ComponentsObject;
use Sabservis\Api\OpenApi\Spec\MediaTypeObject;
use Sabservis\Api\OpenApi\Spec\RequestBodyObject;
use Sabservis\Api\OpenApi\Spec\SchemaObject;

final class ComponentsObjectTest extends TestCase
{

	#[Test]
	public function serializesOnlyNonEmptySections(): void
	{
		$components = new ComponentsObject(
			schemas: ['User' => new SchemaObject(type: 'object')],
			responses: [],
			parameters: null,
			requestBodies: [
				'CreateUser' => new RequestBodyObject(
					content: ['application/json' => new MediaTypeObject()],
				),
			],
			headers: [],
			securitySchemes: null,
			links: [],
		);

		$result = $components->jsonSerialize();

		self::assertArrayHasKey('schemas', $result);
		self::assertArrayHasKey('requestBodies', $result);
		self::assertArrayNotHasKey('responses', $result);
		self::assertArrayNotHasKey('headers', $result);
		self::assertArrayNotHasKey('links', $result);
	}

}
