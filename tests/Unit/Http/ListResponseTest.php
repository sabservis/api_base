<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\ListMeta;
use Sabservis\Api\Http\ListResponse;
use Sabservis\Api\Http\PaginatedListResponse;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use function array_filter;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

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
	public function listResponseJsonSerializesToPlainArray(): void
	{
		$data = [['id' => 1, 'name' => 'First'], ['id' => 2, 'name' => 'Second']];
		$response = new ListResponse($data);

		$json = json_encode($response, JSON_THROW_ON_ERROR);

		self::assertSame('[{"id":1,"name":"First"},{"id":2,"name":"Second"}]', $json);
	}

	#[Test]
	public function listResponseJsonSerializesEmptyArray(): void
	{
		$response = new ListResponse([]);

		$json = json_encode($response, JSON_THROW_ON_ERROR);

		self::assertSame('[]', $json);
	}

	#[Test]
	public function paginatedListResponseReturnsDataAndMeta(): void
	{
		$data = [['id' => 1], ['id' => 2]];
		$response = PaginatedListResponse::create($data, totalCount: 100, limit: 20, offset: 0);

		self::assertSame($data, $response->getData());
		self::assertInstanceOf(ListMeta::class, $response->getMeta());
		self::assertSame(100, $response->getMeta()->totalCount);
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

	#[Test]
	public function paginatedListResponseWithoutMetaJsonSerializesToDataWrapped(): void
	{
		$data = [['id' => 1, 'name' => 'First'], ['id' => 2, 'name' => 'Second']];
		$response = new PaginatedListResponse($data);

		$json = json_encode($response, JSON_THROW_ON_ERROR);
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		self::assertSame(['data' => $data], $decoded);
		self::assertArrayNotHasKey('meta', $decoded);
	}

	#[Test]
	public function paginatedListResponseWithMetaJsonSerializesToDataAndMeta(): void
	{
		$data = [['id' => 1], ['id' => 2]];
		$response = PaginatedListResponse::create($data, totalCount: 100, limit: 20, offset: 0);

		$json = json_encode($response, JSON_THROW_ON_ERROR);
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		self::assertSame($data, $decoded['data']);
		self::assertSame(100, $decoded['meta']['totalCount']);
		self::assertSame(20, $decoded['meta']['limit']);
		self::assertSame(0, $decoded['meta']['offset']);
	}

	#[Test]
	public function paginatedListResponseEmptyDataJsonSerializesToDataWrapped(): void
	{
		$response = new PaginatedListResponse([]);

		$json = json_encode($response, JSON_THROW_ON_ERROR);

		self::assertSame('{"data":[]}', $json);
	}

	#[Test]
	public function listResponseWithGapsInKeysSerializesToJsonArray(): void
	{
		$filtered = array_filter([['id' => 1], ['id' => 2], ['id' => 3]], static fn (array $i): bool => $i['id'] !== 1);

		self::assertSame('[{"id":2},{"id":3}]', json_encode(new ListResponse($filtered), JSON_THROW_ON_ERROR));
		self::assertSame('[{"id":2},{"id":3}]', (new DataMapperSerializer())->serialize(new ListResponse($filtered)));
	}

	#[Test]
	public function paginatedListResponseWithGapsInKeysSerializesToJsonArray(): void
	{
		$data = [3 => ['id' => 3], 7 => ['id' => 7]];
		$expected = '{"data":[{"id":3},{"id":7}],"meta":{"totalCount":10,"limit":2,"offset":0}}';

		self::assertSame($expected, json_encode(PaginatedListResponse::create($data, 10, 2, 0), JSON_THROW_ON_ERROR));
		self::assertSame(
			$expected,
			(new DataMapperSerializer())->serialize(PaginatedListResponse::create($data, 10, 2, 0)),
		);
		self::assertSame(
			'{"data":[{"id":3},{"id":7}]}',
			(new DataMapperSerializer())->serialize(new PaginatedListResponse($data)),
		);
	}

	#[Test]
	public function apiResponseListWithGapsInKeysReturnsList(): void
	{
		$response = ApiResponse::list([5 => ['id' => 5]], unwrapped: true);
		$object = $response->getObject();

		self::assertInstanceOf(ListResponse::class, $object);
		self::assertSame([['id' => 5]], $object->getData());
	}

}
