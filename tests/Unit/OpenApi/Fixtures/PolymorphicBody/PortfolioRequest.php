<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\PolymorphicBody;

use Sabservis\Api\Attribute\OpenApi\Discriminator;
use Sabservis\Api\Attribute\OpenApi\Schema;

#[Schema(oneOf: [InvestmentsPortfolio::class, SavingsPortfolio::class])]
#[Discriminator(
	propertyName: 'type',
	mapping: ['Investments' => InvestmentsPortfolio::class, 'Savings' => SavingsPortfolio::class],
)]
class PortfolioRequest
{

}
