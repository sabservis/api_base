<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use BackedEnum;
use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\BackedEnumTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Tests\Fixtures\Mapping\Types\TestBackedEnum;

Environment::setupFunctions();

final class BackedEnumTypeNormalizerTest extends TestCase
{

	private BackedEnumTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same(TestBackedEnum::FirstCase, $this->normalizer->denormalize('FirstCase', TestBackedEnum::class));
		Assert::same(TestBackedEnum::SecondCase, $this->normalizer->denormalize('SecondCase', TestBackedEnum::class));
		Assert::same(TestBackedEnum::SecondCase, $this->normalizer->denormalize('second-case', TestBackedEnum::class));
		Assert::exception(function (): void {
			$this->normalizer->denormalize('invalid', TestBackedEnum::class);
		}, InvalidArgumentTypeException::class, 'Invalid value for this enum. Possible values are: FirstCase, second-case');
	}

	public function testNormalize(): void
	{
		Assert::same('FirstCase', $this->normalizer->normalize(TestBackedEnum::FirstCase));
		Assert::same('second-case', $this->normalizer->normalize(TestBackedEnum::SecondCase));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same([BackedEnum::class], BackedEnumTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new BackedEnumTypeNormalizer();
	}

}

$test = new BackedEnumTypeNormalizerTest();
$test->run();
