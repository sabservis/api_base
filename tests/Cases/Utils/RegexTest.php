<?php declare(strict_types = 1);

namespace Tests\Cases\Adjuster;

require_once __DIR__ . '/../../bootstrap.php';

use Sabservis\Api\Utils\Regex;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class RegexTest extends TestCase
{

	public function testMatch(): void
	{
		Assert::equal(null, Regex::match('foo', '#\d+#'));
		Assert::equal(['foo'], Regex::match('foo', '#\w+#'));
		Assert::equal(['foo', 'foo'], Regex::match('foo', '#(\w+)#'));
	}

	public function testReplace(): void
	{
		Assert::equal('foo', Regex::replace('foobar', '#bar#', ''));
	}

	public function testReplaceCallback(): void
	{
		Assert::equal('barfoo', Regex::replaceCallback('foo', '#(foo)#', fn ($matches) => 'bar' . $matches[1]));
	}

}

$test = new RegexTest();
$test->run();

