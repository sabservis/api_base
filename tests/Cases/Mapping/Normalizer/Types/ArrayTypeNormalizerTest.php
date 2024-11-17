<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\ArrayTypeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class ArrayTypeNormalizerTest extends TestCase
{

	private ArrayTypeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same([], $this->normalizer->denormalize([]));
		Assert::same(['a' => 1], $this->normalizer->denormalize(['a' => 1]));

		Assert::same(['a' => 1], $this->normalizer->denormalize('{"a":1}'));
		Assert::same(['a' => ['b' => 1]], $this->normalizer->denormalize('{"a":{"b":1}}'));

		Assert::same(['a' => 1], $this->normalizer->denormalize('a:1:{s:1:"a";i:1;}'));
		Assert::same(['a' => ['b' => 1]], $this->normalizer->denormalize('a:1:{s:1:"a";a:1:{s:1:"b";i:1;}}'));

		Assert::exception(function (): void {
			$this->normalizer->denormalize('invalid');
		}, InvalidArgumentTypeException::class, 'The value is not a valid array.');
	}

	public function testNormalize(): void
	{
		Assert::same([], $this->normalizer->normalize([]));
		Assert::same(['a' => 1], $this->normalizer->normalize(['a' => 1]));
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same(['array'], ArrayTypeNormalizer::getSupportedTypes());
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new ArrayTypeNormalizer();
	}

}

$test = new ArrayTypeNormalizerTest();
$test->run();
