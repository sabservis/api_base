<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\OperationObject;
use Sabservis\Api\OpenApi\Spec\PathItemObject;
use Sabservis\Api\OpenApi\Spec\ResponseObject;

final class PathItemObjectTest extends TestCase
{

	#[Test]
	public function withMethodReturnsClonedObjectWithAssignedOperation(): void
	{
		$operation = new OperationObject(
			responses: ['200' => new ResponseObject('OK')],
		);
		$pathItem = new PathItemObject(summary: 'Users');

		$updated = $pathItem->withMethod('PATCH', $operation);

		self::assertNotSame($pathItem, $updated);
		self::assertNull($pathItem->patch);
		self::assertSame('Users', $updated->summary);
		self::assertSame($operation, $updated->patch);
	}

	#[Test]
	public function withMethodRejectsInvalidHttpMethod(): void
	{
		$operation = new OperationObject(
			responses: ['200' => new ResponseObject('OK')],
		);
		$pathItem = new PathItemObject();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid HTTP method "FETCH"');

		$pathItem->withMethod('FETCH', $operation);
	}

	#[Test]
	public function jsonSerializeReturnsOnlyRefWhenSet(): void
	{
		$operation = new OperationObject(
			responses: ['200' => new ResponseObject('OK')],
		);

		$pathItem = new PathItemObject(
			ref: '#/components/pathItems/UserPath',
			summary: 'Ignored when $ref is set',
			get: $operation,
		);

		self::assertSame(['$ref' => '#/components/pathItems/UserPath'], $pathItem->jsonSerialize());
	}

}
