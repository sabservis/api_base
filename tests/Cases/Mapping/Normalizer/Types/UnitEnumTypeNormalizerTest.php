<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\UnitEnumTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Tests\Fixtures\Mapping\Types\TestUnitEnum;
use UnitEnum;

Environment::setupFunctions();

final class UnitEnumTypeNormalizerTest extends TestCase
{

	private UnitEnumTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same(TestUnitEnum::FirstCase, $this->normalizer->denormalize('FirstCase', TestUnitEnum::class));
		Assert::same(TestUnitEnum::SecondCase, $this->normalizer->denormalize('SecondCase', TestUnitEnum::class));
		Assert::exception(function (): void {
			$this->normalizer->denormalize('invalid', TestUnitEnum::class);
		}, InvalidArgumentTypeException::class, 'Invalid value for this enum. Possible values are: FirstCase, SecondCase');
	}

	public function testNormalize(): void
	{
		Assert::same('FirstCase', $this->normalizer->normalize(TestUnitEnum::FirstCase));
		Assert::same('SecondCase', $this->normalizer->normalize(TestUnitEnum::SecondCase));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same([UnitEnum::class], UnitEnumTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new UnitEnumTypeNormalizer();
	}

}

$test = new UnitEnumTypeNormalizerTest();
$test->run();
