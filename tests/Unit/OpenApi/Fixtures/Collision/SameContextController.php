<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\UI\Controller\Controller;
use Tests\Unit\OpenApi\Fixtures\Collision\Invoice\ItemDto as InvoiceItemDto;
use Tests\Unit\OpenApi\Fixtures\Collision\Order\ItemDto as OrderItemDto;
use Tests\Unit\OpenApi\Fixtures\Collision\Shipping\ItemDto as ShippingItemDto;

final class SameContextController implements Controller
{

	/**
	 * @return array<OrderItemDto|InvoiceItemDto>
	 */
	#[Get(path: '/items')]
	#[Response(listRef: [OrderItemDto::class, InvoiceItemDto::class])]
	public function items(): array
	{
		return [];
	}

	#[Get(path: '/items/any')]
	#[Response(
		content: new JsonContent(oneOf: [new Schema(ref: OrderItemDto::class), new Schema(ref: InvoiceItemDto::class)]),
	)]
	public function anyItem(): OrderItemDto|InvoiceItemDto
	{
		return new OrderItemDto();
	}

	#[Post(path: '/items/import')]
	#[RequestBody(
		content: new JsonContent(
			oneOf: [new Schema(ref: InvoiceItemDto::class), new Schema(ref: ShippingItemDto::class)],
		),
	)]
	#[Response(ref: OrderItemDto::class)]
	public function import(): OrderItemDto
	{
		return new OrderItemDto();
	}

	#[Post(path: '/shipping/items')]
	#[RequestBody(ref: ShippingItemDto::class)]
	#[Response(ref: ShippingItemDto::class)]
	public function createShippingItem(ShippingItemDto $item): ShippingItemDto
	{
		return $item;
	}

}
