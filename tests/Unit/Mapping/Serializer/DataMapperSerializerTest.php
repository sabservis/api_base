<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping\Serializer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Property as OpenApiProperty;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Api\ValidationException;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use function json_decode;
use function str_repeat;

final class DataMapperSerializerTest extends TestCase
{

	private DataMapperSerializer $serializer;

	protected function setUp(): void
	{
		$this->serializer = new DataMapperSerializer();
	}

	// =====================
	// serialize() tests
	// =====================

	#[Test]
	public function serializeArray(): void
	{
		$data = ['name' => 'John', 'age' => 30];

		$result = $this->serializer->serialize($data);

		self::assertSame('{"name":"John","age":30}', $result);
	}

	#[Test]
	public function serializeEmptyArray(): void
	{
		$result = $this->serializer->serialize([]);

		self::assertSame('[]', $result);
	}

	#[Test]
	public function serializeNestedArray(): void
	{
		$data = [
			'user' => [
				'name' => 'John',
				'tags' => ['admin', 'user'],
			],
		];

		$result = $this->serializer->serialize($data);

		self::assertSame('{"user":{"name":"John","tags":["admin","user"]}}', $result);
	}

	#[Test]
	public function serializeScalarValues(): void
	{
		self::assertSame('"hello"', $this->serializer->serialize('hello'));
		self::assertSame('123', $this->serializer->serialize(123));
		self::assertSame('true', $this->serializer->serialize(true));
		self::assertSame('null', $this->serializer->serialize(null));
	}

	#[Test]
	public function serializeObject(): void
	{
		$obj = new TestUserDto();
		$obj->name = 'John';
		$obj->email = 'john@example.com';

		$result = $this->serializer->serialize($obj);
		$decoded = json_decode($result, true);

		self::assertSame('John', $decoded['name']);
		self::assertSame('john@example.com', $decoded['email']);
	}

	// =====================
	// deserialize() tests
	// =====================

	#[Test]
	public function deserializeToArray(): void
	{
		$json = '{"name":"John","age":30}';

		$result = $this->serializer->deserialize($json);

		self::assertIsArray($result);
		self::assertSame('John', $result['name']);
		self::assertSame(30, $result['age']);
	}

	#[Test]
	public function deserializeEmptyObject(): void
	{
		$json = '{}';

		$result = $this->serializer->deserialize($json);

		self::assertIsArray($result);
		self::assertSame([], $result);
	}

	#[Test]
	public function deserializeToDto(): void
	{
		$json = '{"name":"John","email":"john@example.com"}';

		$result = $this->serializer->deserialize($json, TestUserDto::class);

		self::assertInstanceOf(TestUserDto::class, $result);
		self::assertSame('John', $result->name);
		self::assertSame('john@example.com', $result->email);
	}

	#[Test]
	public function deserializeToDtoWithOpenApiPropertyOnPromotedParameter(): void
	{
		$json = '{"username":"John"}';

		$result = $this->serializer->deserialize($json, TestPromotedDtoWithOpenApiProperty::class);

		self::assertInstanceOf(TestPromotedDtoWithOpenApiProperty::class, $result);
		self::assertSame('John', $result->username);
	}

	#[Test]
	public function deserializeInvalidJsonThrows(): void
	{
		$invalidJson = '{invalid json}';

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data (JSON)');

		$this->serializer->deserialize($invalidJson);
	}

	#[Test]
	public function deserializeInvalidJsonWithTypeThrows(): void
	{
		$invalidJson = '{not valid}';

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data (JSON)');

		$this->serializer->deserialize($invalidJson, TestUserDto::class);
	}

	#[Test]
	public function deserializeNonStringThrows(): void
	{
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data - expected JSON string');

		$this->serializer->deserialize(['not' => 'string']);
	}

	#[Test]
	public function deserializeIntegerThrows(): void
	{
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data - expected JSON string');

		$this->serializer->deserialize(123);
	}

	#[Test]
	public function deserializeNullThrows(): void
	{
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data - expected JSON string');

		$this->serializer->deserialize(null);
	}

	#[Test]
	public function deserializeValidationErrorThrowsValidationException(): void
	{
		// Invalid type for a field should trigger validation error
		$json = '{"name":"John","age":"not-a-number"}';

		$this->expectException(ValidationException::class);

		$this->serializer->deserialize($json, TestTypedDto::class);
	}

	#[Test]
	public function deserializeWithTypePreservesOriginalException(): void
	{
		$invalidJson = '{invalid}';

		try {
			$this->serializer->deserialize($invalidJson, TestUserDto::class);
			self::fail('Expected ClientErrorException');
		} catch (ClientErrorException $e) {
			self::assertNotNull($e->getPrevious());
		}
	}

	// =====================
	// JSON Depth Limit tests
	// =====================

	#[Test]
	public function deserializeDeeplyNestedJsonThrows(): void
	{
		// Create JSON nested deeper than the 64-level limit
		$depth = 70;
		$json = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data (JSON)');

		$this->serializer->deserialize($json);
	}

	#[Test]
	public function deserializeWithinDepthLimitSucceeds(): void
	{
		// Create JSON nested within the 64-level limit
		$depth = 60;
		$json = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

		$result = $this->serializer->deserialize($json);

		self::assertIsArray($result);
	}

	#[Test]
	public function customJsonDepthCanBeConfigured(): void
	{
		$serializer = new DataMapperSerializer(jsonDepth: 10);

		// Create JSON nested deeper than custom limit
		$depth = 15;
		$json = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Invalid input data (JSON)');

		$serializer->deserialize($json);
	}

	#[Test]
	public function defaultJsonDepthIs64(): void
	{
		self::assertSame(64, DataMapperSerializer::DEFAULT_JSON_DEPTH);
	}

}

/**
 * Test DTO for serialization tests
 */
class TestUserDto
{

	public string $name = '';

	public string $email = '';

}

/**
 * Test DTO with typed fields for validation tests
 */
class TestTypedDto
{

	public string $name = '';

	public int $age = 0;

}

class TestPromotedDtoWithOpenApiProperty
{

	public function __construct(
		#[OpenApiProperty(description: 'Username')]
		public readonly string $username,
	)
	{
	}

}
