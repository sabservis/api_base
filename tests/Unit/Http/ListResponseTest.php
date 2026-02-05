<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\ListMeta;
use Sabservis\Api\Http\ListResponse;

final class ListResponseTest extends TestCase
{
    #[Test]
    public function createWithDataAndMeta(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $response = ListResponse::create($data, total: 100, limit: 20, offset: 0);

        self::assertSame($data, $response->getData());
        self::assertInstanceOf(ListMeta::class, $response->getMeta());
        self::assertSame(100, $response->getMeta()->total);
    }

    #[Test]
    public function createWithoutMeta(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $response = ListResponse::withoutMeta($data);

        self::assertSame($data, $response->getData());
        self::assertNull($response->getMeta());
    }

    #[Test]
    public function toArrayWithMeta(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $response = ListResponse::create($data, total: 50, limit: 10, offset: 0);

        self::assertSame([
            'data' => $data,
            'meta' => [
                'total' => 50,
                'limit' => 10,
                'offset' => 0,
            ],
        ], $response->toArray());
    }

    #[Test]
    public function toArrayWithoutMetaReturnsJustData(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $response = ListResponse::withoutMeta($data);

        // Without meta, toArray returns just the data array
        self::assertSame($data, $response->toArray());
    }

    #[Test]
    public function jsonSerializeWithMeta(): void
    {
        $data = [['id' => 1]];
        $response = ListResponse::create($data, total: 1, limit: 10, offset: 0);

        $expected = '{"data":[{"id":1}],"meta":{"total":1,"limit":10,"offset":0}}';
        self::assertSame($expected, json_encode($response));
    }

    #[Test]
    public function jsonSerializeWithoutMeta(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $response = ListResponse::withoutMeta($data);

        $expected = '[{"id":1},{"id":2}]';
        self::assertSame($expected, json_encode($response));
    }

    #[Test]
    public function hasMeta(): void
    {
        $withMeta = ListResponse::create([], total: 0, limit: 10, offset: 0);
        $withoutMeta = ListResponse::withoutMeta([]);

        self::assertTrue($withMeta->hasMeta());
        self::assertFalse($withoutMeta->hasMeta());
    }
}
