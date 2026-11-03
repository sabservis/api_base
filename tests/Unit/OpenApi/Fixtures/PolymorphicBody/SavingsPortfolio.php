<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\PolymorphicBody;

use Sabservis\Api\Attribute\OpenApi\Property;

class SavingsPortfolio
{

	#[Property(enum: ['Investments', 'Savings'])]
	public string $type;

	#[Property(example: 'Spoření')]
	public string $name;

	#[Property(example: 1_000)]
	public int $monthlyDeposit;

}
