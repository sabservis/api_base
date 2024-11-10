<?php declare(strict_types = 1);

namespace Tests\Cases\Adjuster;

require_once __DIR__ . '/../../bootstrap.php';

use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Utils\Arrays;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class ArraysUtilsTest extends TestCase
{

	/** @var array>mixed> */
	private array $arr;

	public function testSingleItem(): void
	{
		Assert::same('first', Arrays::get($this->arr, ''));
		Assert::same('second', Arrays::get($this->arr, 1));
		Assert::same('second', Arrays::get($this->arr, 1, 'x'));
		Assert::same('x', Arrays::get($this->arr, 'undefined', 'x'));
		Assert::exception(
			fn () => Arrays::get($this->arr, 'undefined'),
			InvalidArgumentException::class,
			"Missing item 'undefined'.",
		);
	}

	public function testTraversing(): void
	{
		Assert::same([
			'' => 'first',
			1 => 'second',
			7 => [
				'item' => 'third',
			],
		], Arrays::get($this->arr, []));

		Assert::same('third', Arrays::get($this->arr, [7, 'item']));
	}

	public function setUp()
	{
		parent::setUp();

		$this->arr = [
			'' => 'first',
			1 => 'second',
			7 => [
				'item' => 'third',
			],
		];
	}

}

$test = new ArraysUtilsTest();
$test->run();

