<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ListMeta;

final class ListMetaTest extends TestCase
{

	#[Test]
	public function createWithAllValues(): void
	{
		$meta = new ListMeta(total: 100, limit: 20, offset: 40);

		self::assertSame(100, $meta->total);
		self::assertSame(20, $meta->limit);
		self::assertSame(40, $meta->offset);
	}

	#[Test]
	public function toArrayReturnsCorrectStructure(): void
	{
		$meta = new ListMeta(total: 50, limit: 10, offset: 0);

		self::assertSame([
			'total' => 50,
			'limit' => 10,
			'offset' => 0,
		], $meta->toArray());
	}

	#[Test]
	public function jsonSerializeReturnsArray(): void
	{
		$meta = new ListMeta(total: 100, limit: 20, offset: 0);

		self::assertSame(
			'{"total":100,"limit":20,"offset":0}',
			json_encode($meta),
		);
	}

}
