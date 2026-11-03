<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Property;

final class JsonContentRequiredTest extends TestCase
{

	#[Test]
	public function inlinePropertiesMarkedRequiredProduceRequiredList(): void
	{
		$content = new JsonContent(
			type: 'object',
			properties: [
				new Property(property: 'id', type: 'integer', required: true),
				new Property(property: 'name', type: 'string', required: true),
				new Property(property: 'note', type: 'string'),
				new Property(property: 'legacy', type: 'string', required: false),
			],
		);

		$schema = $content->toOpenApiSpec()['schema'];

		self::assertSame(['id', 'name'], $schema['required']);
		// required is a schema-level list, never a property keyword
		self::assertArrayNotHasKey('required', $schema['properties']['id']);
	}

	#[Test]
	public function noRequiredListWhenNothingIsRequired(): void
	{
		$content = new JsonContent(
			type: 'object',
			properties: [new Property(property: 'note', type: 'string')],
		);

		self::assertArrayNotHasKey('required', $content->toOpenApiSpec()['schema']);
	}

}
