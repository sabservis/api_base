<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Spec;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Spec\OperationObject;
use Sabservis\Api\OpenApi\Spec\ParameterObject;
use Sabservis\Api\OpenApi\Spec\ResponseObject;

final class OperationObjectTest extends TestCase
{

	#[Test]
	public function extrasDeepMergeAssociativeArraysAndReplaceIndexedArrays(): void
	{
		$operation = new OperationObject(
			responses: ['200' => new ResponseObject('Original response')],
			parameters: [new ParameterObject('id', ParameterObject::InPath)],
			extras: [
				'responses' => [
					'200' => ['description' => 'Overridden response'],
					'500' => ['description' => 'Server error'],
				],
				'parameters' => [
					['name' => 'q', 'in' => 'query'],
				],
				'x-meta' => [
					'feature' => ['enabled' => true],
				],
			],
		);

		$result = $operation->jsonSerialize();

		self::assertSame('Overridden response', $result['responses']['200']['description']);
		self::assertSame('Server error', $result['responses']['500']['description']);
		self::assertSame([['name' => 'q', 'in' => 'query']], $result['parameters']);
		self::assertSame(['feature' => ['enabled' => true]], $result['x-meta']);
	}

	#[Test]
	public function omitsEmptyOptionalCollections(): void
	{
		$operation = new OperationObject(
			responses: ['200' => new ResponseObject('OK')],
			tags: [],
			parameters: [],
			servers: [],
		);

		$result = $operation->jsonSerialize();

		self::assertArrayNotHasKey('tags', $result);
		self::assertArrayNotHasKey('parameters', $result);
		self::assertArrayNotHasKey('servers', $result);
		self::assertArrayHasKey('responses', $result);
	}

}
