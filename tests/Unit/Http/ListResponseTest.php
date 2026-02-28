<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ListMeta;
use Sabservis\Api\Http\ListResponse;
use Sabservis\Api\Http\PaginatedListResponse;

final class ListResponseTest extends TestCase
{

	#[Test]
	public function listResponseReturnsData(): void
	{
		$data = [['id' => 1], ['id' => 2]];
		$response = new ListResponse($data);

		self::assertSame($data, $response->getData());
	}

	#[Test]
	public function paginatedListResponseReturnsDataAndMeta(): void
	{
		$data = [['id' => 1], ['id' => 2]];
		$response = PaginatedListResponse::create($data, total: 100, limit: 20, offset: 0);

		self::assertSame($data, $response->getData());
		self::assertInstanceOf(ListMeta::class, $response->getMeta());
		self::assertSame(100, $response->getMeta()->total);
		self::assertSame(20, $response->getMeta()->limit);
		self::assertSame(0, $response->getMeta()->offset);
	}

	#[Test]
	public function paginatedListResponseWithoutMeta(): void
	{
		$data = [['id' => 1], ['id' => 2]];
		$response = new PaginatedListResponse($data);

		self::assertSame($data, $response->getData());
		self::assertNull($response->getMeta());
	}

}
