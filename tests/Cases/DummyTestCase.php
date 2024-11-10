<?php declare(strict_types = 1);

namespace Tests\Cases\Adjuster;

require_once __DIR__ . '/../../bootstrap.php';

use QaData\Psr7\Psr7ServerRequestFactory;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class DummyTestCase extends TestCase
{

	public function test(): void
	{

	}

}

$test = new DummyTestCase();
$test->run();

