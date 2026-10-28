<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\Schema;

// Pinned name equals the short name of another class (CartDto)
#[Schema(name: 'CartDto')]
final class Basket
{

	public int $total;

}
