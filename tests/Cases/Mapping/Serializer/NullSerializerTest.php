<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Serializer;

require_once __DIR__ . '/../../../bootstrap.php';

use Sabservis\Api\Mapping\Serializer\NullSerializer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class NullSerializerTest extends TestCase
{

	private NullSerializer $serializer;

	public function testSerialize(): void
	{
		Assert::equal('Test', $this->serializer->serialize('Test'));
		Assert::equal('123', $this->serializer->serialize(123));
		Assert::equal('123.05', $this->serializer->serialize(123.05));
		Assert::equal('1', $this->serializer->serialize(true));
		Assert::equal('', $this->serializer->serialize(false));
	}

	public function testDeserialize(): void
	{
		Assert::equal('Test', $this->serializer->deserialize('Test'));
		Assert::equal(123, $this->serializer->deserialize(123));
		Assert::equal(123.05, $this->serializer->deserialize(123.05));
		Assert::equal(true, $this->serializer->deserialize(true));
		Assert::equal(false, $this->serializer->deserialize(false));
	}

	public function setUp(): void
	{
		$this->serializer = new NullSerializer();

		parent::setUp();
	}

}

$test = new NullSerializerTest();
$test->run();
