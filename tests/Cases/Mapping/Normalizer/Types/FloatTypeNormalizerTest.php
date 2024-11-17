<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\FloatTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class FloatTypeNormalizerTest extends TestCase
{

	private FloatTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same(1.0, $this->normalizer->denormalize(1));
		Assert::same(1.1, $this->normalizer->denormalize(1.1));
		Assert::same(1.0, $this->normalizer->denormalize('1'));
		Assert::same(1.1, $this->normalizer->denormalize('1.1'));
		Assert::same(1.1, $this->normalizer->denormalize('1.10000000000'));
		Assert::same(1.123_456_789, $this->normalizer->denormalize('1.123456789'));
		Assert::same(123_456_789.123, $this->normalizer->denormalize('123456789,123'));
		Assert::same(1.2, $this->normalizer->denormalize('1,2'));
		Assert::exception(function (): void {
			$this->normalizer->denormalize('invalid');
		}, InvalidArgumentTypeException::class, 'This value is not float number.');
	}

	public function testNormalize(): void
	{
		Assert::same(1.0, $this->normalizer->normalize(1));
		Assert::same(1.1, $this->normalizer->normalize(1.1));
		Assert::same(1.123_456_789, $this->normalizer->denormalize('1.123456789'));
		Assert::same(123_456_789.123, $this->normalizer->denormalize('123456789,123'));
		Assert::same(1.1, $this->normalizer->normalize(1.100_000_000_000));
		Assert::same(1.100_000_000_000_1, $this->normalizer->normalize(1.100_000_000_000_1));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same(['float', 'double', 'decimal', 'number'], FloatTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new FloatTypeNormalizer();
	}

}

$test = new FloatTypeNormalizerTest();
$test->run();
