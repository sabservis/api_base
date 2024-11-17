<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use DateTimeImmutable;
use Sabservis\Api\Mapping\Entity\Entity;
use Sabservis\Api\Mapping\Normalizer\NormalizationErrorCollection;
use Sabservis\Api\Mapping\Normalizer\TypeNormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Types;
use Sabservis\Api\Mapping\Normalizer\Types\ObjectTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Tests\Fixtures\Mapping\Types\TestBackedEnum;
use Tests\Fixtures\Mapping\Types\TestNestedObject;
use Tests\Fixtures\Mapping\Types\TestObject;
use Tests\Fixtures\Mapping\Types\TestUnitEnum;
use function assert;
use function serialize;

Environment::setupFunctions();

final class ObjectTypeNormalizerTest extends TestCase
{

	private ObjectTypeNormalizer $normalizer;

	public function testDenormalizeIntegerProperties(): void
	{
		$input = [
			'integer' => 1,
			'integerNullable' => null,
			'integerWithDefault' => 2,
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// Integer
		Assert::same(1, $result->integer);
		Assert::same(2, $result->integerWithDefault);
		Assert::null($result->integerNullable);
		// Assert::same(9, $result->integerNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!
	}

	public function testDenormalizeFloatProperties(): void
	{
		$input = [
			'float' => 1.1,
			'floatNullable' => null,
			'floatWithDefault' => 2.2,
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// Float
		Assert::same(1.1, $result->float);
		Assert::same(2.2, $result->floatWithDefault);
		Assert::null($result->floatNullable);
		// Assert::same(3.3, $result->floatNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!
	}

	public function testDenormalizeStringProperties(): void
	{
		$input = [
			'string' => 'string',
			'stringNullable' => null,
			'stringWithDefault' => 'string2',
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// String
		Assert::same('string', $result->string);
		Assert::same('string2', $result->stringWithDefault);
		Assert::null($result->stringNullable);
		// Assert::same('string3', $result->stringNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!
	}

	public function testDenormalizeBooleanProperties(): void
	{
		$input = [
			'boolean' => true,
			'booleanNullable' => null,
			'booleanWithDefault' => false,
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// Boolean
		Assert::true($result->boolean);
		Assert::false($result->booleanWithDefault);
		Assert::null($result->booleanNullable);
		// Assert::true($result->booleanNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!
	}

	public function testDenormalizeDateProperties(): void
	{
		$input = [
			'dateTime' => '2021-01-01 00:00:01',
			'dateTimeNullable' => null,
			'dateTimeWithDefault' => '2021-01-01 00:00:01',
			'dateTimeWithDenormalizerForYmd' => '2021-02-03',
			'dateTimeWithDenormalizerForYmdHis' => '2021-02-03 00:00:01',
			'dateTimeWithDenormalizerForYmdHisAndWithTimezone' => '2021-02-03 12:00:00',
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// Date
		Assert::type(DateTimeImmutable::class, $result->dateTime);
		Assert::same('2021-01-01 00:00:01', $result->dateTime->format('Y-m-d H:i:s'));
		Assert::type(DateTimeImmutable::class, $result->dateTimeWithDefault);
		Assert::same('2021-01-01 00:00:01', $result->dateTimeWithDefault->format('Y-m-d H:i:s'));
		Assert::null($result->dateTimeNullable);
		// Assert::type(\DateTimeImmutable::class, $result->dateTimeNullableWithDefault);
		// Assert::same('2021-01-01 00:00:01', $result->dateTimeNullableWithDefault->format('Y-m-d H:i:s')); // TODO Chyba, má tu být výchozí hodnota!

		Assert::type(DateTimeImmutable::class, $result->dateTimeWithDenormalizerForYmdHis);
		Assert::same('2021-02-03 00:00:01', $result->dateTimeWithDenormalizerForYmdHis->format('Y-m-d H:i:s'));
		Assert::type(DateTimeImmutable::class, $result->dateTimeWithDenormalizerForYmdHisAndWithTimezone);
		Assert::same(
			'2021-02-03 12:00:00-06:00',
			$result->dateTimeWithDenormalizerForYmdHisAndWithTimezone->format('Y-m-d H:i:sP'),
		);
		Assert::type(DateTimeImmutable::class, $result->dateTimeWithDenormalizerForYmd);
		Assert::same('2021-02-03', $result->dateTimeWithDenormalizerForYmd->format('Y-m-d'));
	}

	public function denormalizeArrayProperties(): void
	{
		$input = [
			'array' => [],
			'arrayNullable' => null,
			'arrayWithDefault' => ['test', 'test2'],
			'arrayWithObjects' => [
				['integer' => 1, 'string' => 'test', 'boolean' => true, 'dateTime' => '2021-01-01 00:00:01', 'array' => ['test', 'test2'], 'backedEnum' => 'FirstCase', 'unitEnum' => 'FirstCase'],
				['integer' => 2, 'string' => 'test2', 'boolean' => false, 'dateTime' => '2021-01-01 00:00:02', 'array' => ['test2', 'test3'], 'backedEnum' => 'SecondCase', 'unitEnum' => 'SecondCase'],
			],
			'arrayWithSerializedInput' => serialize(['test', 'test2']),
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// Array
		Assert::same([], $result->array);
		Assert::same(['test', 'test2'], $result->arrayWithDefault);
		Assert::null($result->arrayNullable);
		Assert::same(['test', 'test2'], $result->arrayWithSerializedInput);
		// Assert::same(['a', 'b', 'c'], $result->arrayNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!

		//Array of objects
		Assert::count(2, $result->arrayWithObjects);
		Assert::type(TestObject::class, $result->arrayWithObjects[0]);
		Assert::same(1, $result->arrayWithObjects[0]->integer);
		Assert::same('test', $result->arrayWithObjects[0]->string);
		Assert::true($result->arrayWithObjects[0]->boolean);
		Assert::type(DateTimeImmutable::class, $result->arrayWithObjects[0]->dateTime);
		Assert::same('2021-01-01 00:00:01', $result->arrayWithObjects[0]->dateTime->format('Y-m-d H:i:s'));
		Assert::same(['test', 'test2'], $result->arrayWithObjects[0]->array);
		Assert::same(TestBackedEnum::FirstCase, $result->arrayWithObjects[0]->backedEnum);
		Assert::same(TestUnitEnum::FirstCase, $result->arrayWithObjects[0]->unitEnum);

		Assert::type(TestObject::class, $result->arrayWithObjects[1]);
		Assert::same(2, $result->arrayWithObjects[1]->integer);
		Assert::same('test2', $result->arrayWithObjects[1]->string);
		Assert::false($result->arrayWithObjects[1]->boolean);
		Assert::type(DateTimeImmutable::class, $result->arrayWithObjects[1]->dateTime);
		Assert::same('2021-01-01 00:00:02', $result->arrayWithObjects[1]->dateTime->format('Y-m-d H:i:s'));
		Assert::same(['test2', 'test3'], $result->arrayWithObjects[1]->array);
		Assert::same(TestBackedEnum::SecondCase, $result->arrayWithObjects[1]->backedEnum);
		Assert::same(TestUnitEnum::SecondCase, $result->arrayWithObjects[1]->unitEnum);
	}

	public function testDenormalizeBackedEnumProperties(): void
	{
		$input = [
			'backedEnum' => 'FirstCase',
			'backedEnumNullable' => null,
			'backedEnumWithDefault' => 'SecondCase',
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// BackedEnum
		Assert::same(TestBackedEnum::FirstCase, $result->backedEnum);
		Assert::same(TestBackedEnum::SecondCase, $result->backedEnumWithDefault);
		Assert::null($result->backedEnumNullable);
		// Assert::same(TestBackedEnum::FirstCase, $result->backedEnumNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!
	}

	public function testDenormalizeUnitEnumProperties(): void
	{
		$input = [
			'unitEnum' => 'FirstCase',
			'unitEnumNullable' => null,
			'unitEnumWithDefault' => 'SecondCase',
		];

		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// UnitEnum
		Assert::same(TestUnitEnum::FirstCase, $result->unitEnum);
		Assert::same(TestUnitEnum::SecondCase, $result->unitEnumWithDefault);
		Assert::null($result->unitEnumNullable);
		// Assert::same(TestUnitEnum::FirstCase, $result->unitEnumNullableWithDefault); // TODO Chyba, má tu být výchozí hodnota!
	}

	public function testObjectProperties(): void
	{
		$input = [
			'object' => [
				'array' => ['test', 'test2'],
				'backedEnum' => 'FirstCase',
				'boolean' => true,
				'dateTime' => '2021-01-01 00:00:01',
				'integer' => 1,
				'string' => 'test',
				'unitEnum' => 'FirstCase',
			],
			'objectNullable' => null,
		];
		$result = $this->normalizer->denormalize($input, TestObject::class);
		assert($result instanceof TestObject);
		Assert::type(TestObject::class, $result);

		// Object
		Assert::type(TestNestedObject::class, $result->object);
		Assert::same(1, $result->object->integer);
		Assert::same('test', $result->object->string);
		Assert::true($result->object->boolean);
		Assert::type(DateTimeImmutable::class, $result->object->dateTime);
		Assert::same('2021-01-01 00:00:01', $result->object->dateTime->format('Y-m-d H:i:s'));
		Assert::same(['test', 'test2'], $result->object->array);
		Assert::same(TestBackedEnum::FirstCase, $result->object->backedEnum);
		Assert::same(TestUnitEnum::FirstCase, $result->object->unitEnum);

		Assert::null($result->objectNullable);
	}

	public function testNestedObjectProperties(): void
	{
		$input = [
			'array' => ['test1'],
			'dateTime' => '2021-01-01 00:00:01',
			'integer' => 1,
			'nestedObject' => [
				'array' => ['test2'],
				'dateTime' => '2021-01-01 00:00:02',
				'integer' => 2,
				'nestedObject' => [
					'array' => ['test3'],
					'dateTime' => '2021-01-01 00:00:03',
					'integer' => 3,
					'nestedObject' => [
						'array' => ['test4'],
						'dateTime' => '2021-01-01 00:00:04',
						'integer' => 4,
						'string' => 'test4',
					],
					'string' => 'test3',
				],
				'string' => 'test',
			],
			'string' => 'test1',
		];
		$result = $this->normalizer->denormalize($input, TestNestedObject::class);
		assert($result instanceof TestNestedObject);
		Assert::type(TestNestedObject::class, $result);

		// First level
		Assert::same(1, $result->integer);
		Assert::same('test1', $result->string);
		Assert::type(DateTimeImmutable::class, $result->dateTime);
		Assert::same('2021-01-01 00:00:01', $result->dateTime->format('Y-m-d H:i:s'));
		Assert::same(['test1'], $result->array);

		// Second level
		Assert::type(TestNestedObject::class, $result->nestedObject);
		Assert::same(2, $result->nestedObject->integer);
		Assert::same('test', $result->nestedObject->string);
		Assert::type(DateTimeImmutable::class, $result->nestedObject->dateTime);
		Assert::same('2021-01-01 00:00:02', $result->nestedObject->dateTime->format('Y-m-d H:i:s'));
		Assert::same(['test2'], $result->nestedObject->array);

		// Third level
		Assert::type(TestNestedObject::class, $result->nestedObject->nestedObject);
		Assert::same(3, $result->nestedObject->nestedObject->integer);
		Assert::same('test3', $result->nestedObject->nestedObject->string);
		Assert::type(DateTimeImmutable::class, $result->nestedObject->nestedObject->dateTime);
		Assert::same('2021-01-01 00:00:03', $result->nestedObject->nestedObject->dateTime->format('Y-m-d H:i:s'));
		Assert::same(['test3'], $result->nestedObject->nestedObject->array);

		// Fourth level
		Assert::type(TestNestedObject::class, $result->nestedObject->nestedObject->nestedObject);
		Assert::same(4, $result->nestedObject->nestedObject->nestedObject->integer);
		Assert::same('test4', $result->nestedObject->nestedObject->nestedObject->string);
		Assert::type(DateTimeImmutable::class, $result->nestedObject->nestedObject->nestedObject->dateTime);
		Assert::same(
			'2021-01-01 00:00:04',
			$result->nestedObject->nestedObject->nestedObject->dateTime->format('Y-m-d H:i:s'),
		);
		Assert::same(['test4'], $result->nestedObject->nestedObject->nestedObject->array);
	}

	public function testNormalize(): void
	{
		$object = new TestObject();
		$object->integer = 1;
		$object->integerWithDefault = 2;
		$object->float = 1.1;
		$object->floatWithDefault = 2.2;
		$object->string = 'string';
		$object->stringWithDefault = 'string2';
		$object->boolean = true;
		$object->booleanWithDefault = true;
		$object->dateTime = new DateTimeImmutable('2021-01-01 01:00:01');
		$object->dateTimeWithNormalizerForYmd = new DateTimeImmutable('2021-01-02');
		$object->dateTimeWithNormalizerForYmdHis = new DateTimeImmutable('2021-01-02 12:24:46');
		$object->dateTimeWithNormalizerForYmdHisAndWithTimezone = new DateTimeImmutable('2021-01-02 12:24:46-06:00');
		$object->array = [];
		$object->arrayWithDefault = ['test', 'test2'];
		$object->arrayNullable = null;

		$obj = new TestNestedObject();
		$obj->integer = 1;
		$obj->string = 'test';
		$obj->boolean = true;
		$obj->dateTime = new DateTimeImmutable('2021-01-01 00:00:01');
		$obj->array = ['test', 'test2'];
		$obj->backedEnum = TestBackedEnum::FirstCase;
		$obj->unitEnum = TestUnitEnum::FirstCase;
		$object->arrayWithObjects[] = $obj;

		$obj = new TestNestedObject();
		$obj->integer = 2;
		$obj->string = 'test2';
		$obj->boolean = false;
		$obj->dateTime = new DateTimeImmutable('2021-01-01 00:00:02');
		$obj->array = ['test2', 'test3'];
		$obj->backedEnum = TestBackedEnum::SecondCase;
		$obj->unitEnum = TestUnitEnum::SecondCase;
		$object->arrayWithObjects[] = $obj;

		$object->backedEnum = TestBackedEnum::FirstCase;
		$object->backedEnumWithDefault = TestBackedEnum::SecondCase;
		$object->unitEnum = TestUnitEnum::FirstCase;

		$nestedObject = new TestNestedObject();
		$nestedObject->integer = 1;
		$nestedObject->string = 'test';
		$nestedObject->boolean = true;
		$nestedObject->dateTime = new DateTimeImmutable('2021-01-01 12:23:34');
		$nestedObject->array = ['test', 'test2'];
		$nestedObject->backedEnum = TestBackedEnum::FirstCase;
		$nestedObject->unitEnum = TestUnitEnum::FirstCase;
		$object->object = $nestedObject;

		Assert::same([
			'array' => [],
			'arrayNullable' => null,
			'arrayNullableWithDefault' => ['a', 'b', 'c'],
			'arrayWithDefault' => ['test', 'test2'],
			'arrayWithObjects' => [
				[
					'array' => ['test', 'test2'],
					'backedEnum' => 'FirstCase',
					'boolean' => true,
					'dateTime' => '2021-01-01 00:00:01',
					'integer' => 1,
					'nestedObject' => null,
					'string' => 'test',
					'unitEnum' => 'FirstCase',
				],
				[
					'array' => ['test2', 'test3'],
					'backedEnum' => 'second-case',
					'boolean' => false,
					'dateTime' => '2021-01-01 00:00:02',
					'integer' => 2,
					'nestedObject' => null,
					'string' => 'test2',
					'unitEnum' => 'SecondCase',
				],
			],
			'backedEnum' => 'FirstCase',
			'backedEnumNullable' => null,
			'backedEnumNullableWithDefault' => 'FirstCase',
			'backedEnumWithDefault' => 'second-case',
			'boolean' => true,
			'booleanNullable' => null,
			'booleanNullableWithDefault' => true,
			'booleanWithDefault' => true,
			'dateTime' => '2021-01-01 01:00:01',
			'dateTimeNullable' => null,
			'dateTimeNullableWithDefault' => '2021-01-03 00:00:00',
			'dateTimeWithDefault' => '2021-01-07 00:00:09',
			'dateTimeWithNormalizerForYmd' => '2021-01-02',
			'dateTimeWithNormalizerForYmdHis' => '2021-01-02 12:24:46',
			'dateTimeWithNormalizerForYmdHisAndWithTimezone' => '2021-01-02 12:24:46-06:00',
			'float' => 1.1,
			'floatNullable' => null,
			'floatNullableWithDefault' => 1.1,
			'floatWithDefault' => 2.2,
			'integer' => 1,
			'integerNullable' => null,
			'integerNullableWithDefault' => 9,
			'integerWithDefault' => 2,
			'object' => [
				'array' => ['test', 'test2'],
				'backedEnum' => 'FirstCase',
				'boolean' => true,
				'dateTime' => '2021-01-01 12:23:34',
				'integer' => 1,
				'nestedObject' => null,
				'string' => 'test',
				'unitEnum' => 'FirstCase',
			],
			'objectNullable' => null,
			'string' => 'string',
			'stringNullable' => null,
			'stringNullableWithDefault' => 'string',
			'stringWithDefault' => 'string2',
			'unitEnum' => 'FirstCase',
			'unitEnumNullable' => null,
			'unitEnumNullableWithDefault' => 'FirstCase',
			'unitEnumWithDefault' => 'FirstCase',
		], $this->normalizer->normalize($object));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same([Entity::class, 'object'], ObjectTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$normalizers = [
			Types\ArrayTypeNormalizer::class,
			Types\BackedEnumTypeNormalizer::class,
			Types\BooleanTypeNormalizer::class,
			Types\DateTimeNormalizer::class,
			Types\FloatTypeNormalizer::class,
			Types\IntegerTypeNormalizer::class,
			Types\ObjectTypeNormalizer::class,
			Types\StringTypeNormalizer::class,
			Types\UnitEnumTypeNormalizer::class,
		];

		$provider = new TypeNormalizerProvider();

		foreach ($normalizers as $normalizer) {
			$provider->addNormalizer($normalizer);
		}

		$normalizer = $provider->findNormalizer('object');
		assert($normalizer instanceof ObjectTypeNormalizer);
		$normalizer->setErrorCollection(new NormalizationErrorCollection());

		$this->normalizer = $normalizer;
	}

}

$test = new ObjectTypeNormalizerTest();
$test->run();
