<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pocta\DataMapper\Attributes\MapProperty;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\OpenApi\Generator\SchemaBuilder;
use function array_keys;
use function json_decode;
use function json_encode;
use function sort;
use const JSON_THROW_ON_ERROR;

final class SerializerNamingSchemaTest extends TestCase
{

	#[Test]
	public function mapPropertyNameIsUsedAsSchemaPropertyName(): void
	{
		$schema = $this->schema(MappedNameDto::class);

		self::assertArrayHasKey('user_name', $schema['properties']);
		self::assertArrayNotHasKey('userName', $schema['properties']);
		self::assertContains('user_name', $schema['required']);
		self::assertNotContains('userName', $schema['required']);
	}

	#[Test]
	public function explicitOpenApiPropertyNameStillWins(): void
	{
		$schema = $this->schema(MappedNameDto::class);

		self::assertArrayHasKey('documented', $schema['properties']);
		self::assertArrayNotHasKey('wire', $schema['properties']);
	}

	#[Test]
	public function schemaPropertiesMatchSerializedJsonKeys(): void
	{
		$serialized = json_decode(
			(new DataMapperSerializer())->serialize(new WireContractDto()),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
		$wireKeys = array_keys($serialized);
		$schemaKeys = array_keys($this->schema(WireContractDto::class)['properties']);
		sort($wireKeys);
		sort($schemaKeys);

		self::assertSame($wireKeys, $schemaKeys);
	}

	/**
	 * @param class-string $class
	 * @return array<string, mixed>
	 */
	private function schema(string $class): array
	{
		$builder = new SchemaBuilder();
		$name = $builder->registerClass($class);

		return json_decode(
			json_encode($builder->getSchemas()[$name], JSON_THROW_ON_ERROR),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
	}

}

final class MappedNameDto
{

	#[MapProperty(name: 'user_name')]
	public string $userName;

	#[Property(property: 'documented')]
	#[MapProperty(name: 'wire')]
	public string $both = '';

	public string $plain = '';

}

final class WireContractDto
{

	#[MapProperty(name: 'created_at')]
	public string $createdAt = '2024-01-01';

	#[MapProperty(name: 'is_active')]
	public bool $isActive = true;

	public string $name = 'x';

}
