<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\UI\Controller\Controller;
use Tests\Unit\OpenApi\Fixtures\Collision\Invoice\ItemDto as InvoiceItemDto;
use Tests\Unit\OpenApi\Fixtures\Collision\Order\ItemDto as OrderItemDto;

final class CollisionController implements Controller
{

	#[Get(path: '/orders/item')]
	#[Response(ref: OrderItemDto::class)]
	public function orderItem(): OrderItemDto
	{
		return new OrderItemDto();
	}

	#[Get(path: '/invoices/item')]
	#[Response(ref: InvoiceItemDto::class)]
	public function invoiceItem(): InvoiceItemDto
	{
		return new InvoiceItemDto();
	}

	#[Get(path: '/cart')]
	#[Response(ref: CartDto::class)]
	public function cart(): CartDto
	{
		return new CartDto();
	}

}
