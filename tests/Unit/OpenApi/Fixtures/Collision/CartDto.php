<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\Property;
use Tests\Unit\OpenApi\Fixtures\Collision\Invoice\ItemDto as InvoiceItemDto;
use Tests\Unit\OpenApi\Fixtures\Collision\Order\ItemDto as OrderItemDto;

final class CartDto
{

	public OrderItemDto $orderItem;

	/** @var array<InvoiceItemDto> */
	#[Property(type: 'array', items: new Items(ref: InvoiceItemDto::class))]
	public array $invoiceItems;

}
