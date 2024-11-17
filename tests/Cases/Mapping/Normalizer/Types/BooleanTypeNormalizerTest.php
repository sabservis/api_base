<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\BooleanTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class BooleanTypeNormalizerTest extends TestCase
{

	private BooleanTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same(true, $this->normalizer->denormalize(true));
		Assert::same(true, $this->normalizer->denormalize('true'));
		Assert::same(true, $this->normalizer->denormalize(1));
		Assert::same(true, $this->normalizer->denormalize('1'));
		Assert::same(false, $this->normalizer->denormalize(false));
		Assert::same(false, $this->normalizer->denormalize('false'));
		Assert::same(false, $this->normalizer->denormalize(0));
		Assert::same(false, $this->normalizer->denormalize('0'));
		Assert::same(false, $this->normalizer->denormalize(null));
		Assert::exception(function (): void {
			$this->normalizer->denormalize('invalid');
		}, InvalidArgumentTypeException::class, 'This value is not boolean.');
	}

	public function testNormalize(): void
	{
		Assert::same(true, $this->normalizer->normalize(true));
		Assert::same(true, $this->normalizer->normalize(1));
		Assert::same(false, $this->normalizer->normalize(false));
		Assert::same(false, $this->normalizer->normalize(0));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same(['bool', 'boolean'], BooleanTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new BooleanTypeNormalizer();
	}

}

$test = new BooleanTypeNormalizerTest();
$test->run();
