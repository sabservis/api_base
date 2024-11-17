<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\IntegerTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class IntegerTypeNormalizerTest extends TestCase
{

	private IntegerTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same(123, $this->normalizer->denormalize(123));
		Assert::same(123, $this->normalizer->denormalize('123'));
		Assert::same(-123, $this->normalizer->denormalize('-123'));
		Assert::same(0, $this->normalizer->denormalize('0'));
		Assert::exception(function (): void {
			$this->normalizer->denormalize('invalid');
		}, InvalidArgumentTypeException::class, 'This value is not integer.');
	}

	public function testNormalize(): void
	{
		Assert::same(123, $this->normalizer->normalize(123));
		Assert::same(123, $this->normalizer->normalize('123'));
		Assert::same(-123, $this->normalizer->normalize('-123'));
		Assert::same(0, $this->normalizer->normalize('0'));
		Assert::same(1, $this->normalizer->normalize(true));
		Assert::same(0, $this->normalizer->normalize(false));
		Assert::same(0, $this->normalizer->normalize(null));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same(['int', 'integer'], IntegerTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new IntegerTypeNormalizer();
	}

}

$test = new IntegerTypeNormalizerTest();
$test->run();
