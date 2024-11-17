<?php declare(strict_types = 1);

namespace Tests\Cases\Utils;

require_once __DIR__ . '/../../bootstrap.php';

use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Utils\Helpers;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class HelpersTest extends TestCase
{

	public function testSlashless(): void
	{
		Assert::equal('/', Helpers::slashless('/'));
		Assert::equal('/', Helpers::slashless('//'));
		Assert::equal('/', Helpers::slashless('/////'));
		Assert::equal('/foo', Helpers::slashless('/foo'));
		Assert::equal('/foo', Helpers::slashless('//foo'));
		Assert::equal('/foo/', Helpers::slashless('/foo/'));
		Assert::equal('/foo/', Helpers::slashless('//foo//'));
	}

	public function testCallback(): void
	{
		Assert::type('callable', Helpers::callback([Helpers::class, 'callback']));

		Assert::exception(static function (): void {
			Assert::type('callable', Helpers::callback([Helpers::class, 'fake']));
		}, InvalidArgumentException::class);
	}

}

$test = new HelpersTest();
$test->run();

