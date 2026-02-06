<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\ParameterObject;
use Sabservis\Api\OpenApi\Spec\SchemaObject;

final class ParameterObjectTest extends TestCase
{

	#[Test]
	public function serializesRequiredAndOptionalFields(): void
	{
		$parameter = new ParameterObject(
			name: 'id',
			in: ParameterObject::InPath,
			required: true,
			allowEmptyValue: false,
			explode: false,
			schema: new SchemaObject(type: 'integer'),
			example: 123,
			examples: ['exampleA' => ['value' => 123]],
		);

		$result = $parameter->jsonSerialize();

		self::assertSame('id', $result['name']);
		self::assertSame('path', $result['in']);
		self::assertTrue($result['required']);
		self::assertFalse($result['allowEmptyValue']);
		self::assertFalse($result['explode']);
		self::assertSame(123, $result['example']);
		self::assertArrayHasKey('schema', $result);
		self::assertArrayHasKey('examples', $result);
	}

}
