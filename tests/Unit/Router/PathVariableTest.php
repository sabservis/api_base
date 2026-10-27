<?php declare(strict_types = 1);

namespace Tests\Unit\Router;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Router\PathVariable;
use function preg_match;
use function str_repeat;

final class PathVariableTest extends TestCase
{

	#[Test]
	public function validGroupNamesAreKept(): void
	{
		foreach (['id', 'userId', 'user_id', '_x', str_repeat('a', 32)] as $name) {
			self::assertSame($name, PathVariable::groupName($name));
		}
	}

	#[Test]
	public function invalidNamesGetValidDistinctGroupNames(): void
	{
		$names = ['user-id', '1code', str_repeat('a', 33), 'user-id-2'];
		$groups = [];

		foreach ($names as $name) {
			$group = PathVariable::groupName($name);
			self::assertSame(1, preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,31}\z/', $group), $name);
			self::assertSame($group, PathVariable::groupName($name), 'deterministic');
			$groups[$group] = true;
		}

		self::assertCount(4, $groups);
	}

}
