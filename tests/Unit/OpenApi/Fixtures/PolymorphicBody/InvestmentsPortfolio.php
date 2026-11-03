<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\PolymorphicBody;

use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;

#[Schema(description: 'Investiční portfolio')]
class InvestmentsPortfolio
{

	#[Property(enum: ['Investments', 'Savings'])]
	public string $type;

	#[Property(example: 'Moje investice')]
	public string $name;

	#[Property(enum: ['CZK'])]
	public string $currency;

	public string|null $note = null;

}
