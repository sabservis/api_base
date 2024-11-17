<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer;

require_once __DIR__ . '/../../../bootstrap.php';

use Sabservis\Api\Mapping\Normalizer\NormalizationErrorCollection;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class NormalizationErrorCollectionTest extends TestCase
{

	public function testAddError(): void
	{
		$collection = new NormalizationErrorCollection();

		Assert::false($collection->hasErrors());
		Assert::count(0, $collection->getErrors());

		$collection->addError('field', 'message');
		Assert::true($collection->hasErrors());
		Assert::count(1, $collection->getErrors());
		Assert::same([['field' => 'field', 'message' => 'message']], $collection->getErrors());
	}

}

$test = new NormalizationErrorCollectionTest();
$test->run();
