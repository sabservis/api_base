<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision\Shipping;

use Sabservis\Api\Attribute\OpenApi\Schema;

#[Schema(name: 'ShippingLine')]
final class ItemDto
{

	public string $carrier;

}
