<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\UI\Controller\Controller;
use Tests\Unit\OpenApi\Fixtures\Collision\Order\ItemDto as OrderItemDto;
use Tests\Unit\OpenApi\Fixtures\Collision\Shipping\ItemDto as ShippingItemDto;

final class PinnedNameController implements Controller
{

	#[Get(path: '/orders/item')]
	#[Response(ref: OrderItemDto::class)]
	public function orderItem(): OrderItemDto
	{
		return new OrderItemDto();
	}

	#[Get(path: '/shipping/item')]
	#[Response(ref: ShippingItemDto::class)]
	public function shippingItem(): ShippingItemDto
	{
		return new ShippingItemDto();
	}

}
