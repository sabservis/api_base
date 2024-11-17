<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use Sabservis\Api\Mapping\Normalizer\Types\StringTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class StringTypeNormalizerTest extends TestCase
{

	private StringTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same('test', $this->normalizer->denormalize('test'));
		Assert::same('123', $this->normalizer->denormalize(123));
		Assert::same('123.456', $this->normalizer->denormalize(123.456));
		Assert::same('1', $this->normalizer->denormalize(true));
		Assert::same('', $this->normalizer->denormalize(false));
		Assert::same('', $this->normalizer->denormalize(null));
	}

	public function testNormalize(): void
	{
		Assert::same('test', $this->normalizer->normalize('test'));
		Assert::same('123', $this->normalizer->normalize(123));
		Assert::same('123.456', $this->normalizer->normalize(123.456));
		Assert::same('1', $this->normalizer->normalize(true));
		Assert::same('', $this->normalizer->normalize(false));
		Assert::same('', $this->normalizer->normalize(null));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same(['string', 'text'], StringTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new StringTypeNormalizer();
	}

}

$test = new StringTypeNormalizerTest();
$test->run();
