<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Tests\Unit\OpenApi\Fixtures\PolymorphicBody\PortfolioController;
use function array_keys;
use function json_decode;
use function json_encode;
use function strlen;
use function substr;
use const JSON_THROW_ON_ERROR;

final class PolymorphicRequestBodyExamplesTest extends TestCase
{

	#[Test]
	public function polymorphicRequestBodyGetsOneExamplePerDiscriminatorValue(): void
	{
		$spec = $this->spec();
		$json = $spec['paths']['/portfolios']['post']['requestBody']['content']['application/json'];

		self::assertSame(['$ref' => '#/components/schemas/PortfolioRequest'], $json['schema']);
		self::assertSame(['Investments', 'Savings'], array_keys($json['examples']));
		self::assertSame(
			[
				'summary' => 'InvestmentsPortfolio',
				'description' => 'Investiční portfolio',
				'value' => ['type' => 'Investments', 'name' => 'Moje investice', 'currency' => 'CZK'],
			],
			$json['examples']['Investments'],
		);
		self::assertSame(
			[
				'summary' => 'SavingsPortfolio',
				'value' => ['type' => 'Savings', 'name' => 'Spoření', 'monthlyDeposit' => 1_000],
			],
			$json['examples']['Savings'],
		);
	}

	#[Test]
	public function jsonContentRefGetsExamplesToo(): void
	{
		$json = $this->spec()['paths']['/portfolios/json-content']['post']['requestBody']['content']['application/json'];

		self::assertSame(['Investments', 'Savings'], array_keys($json['examples']));
	}

	#[Test]
	public function everyExampleSatisfiesItsVariant(): void
	{
		$spec = $this->spec();
		$root = $spec['components']['schemas']['PortfolioRequest'];
		$examples = $spec['paths']['/portfolios']['post']['requestBody']['content']['application/json']['examples'];

		foreach ($root['discriminator']['mapping'] as $value => $ref) {
			$variant = $spec['components']['schemas'][substr($ref, strlen('#/components/schemas/'))];
			$example = $examples[$value]['value'];

			foreach ($variant['required'] as $required) {
				self::assertArrayHasKey($required, $example, $value . ' misses required ' . $required);
			}

			self::assertSame($value, $example[$root['discriminator']['propertyName']]);
			self::assertContains($value, $variant['properties']['type']['enum']);
		}
	}

	#[Test]
	public function manualExamplesWin(): void
	{
		$json = $this->spec()['paths']['/portfolios/manual-examples']['post']['requestBody']['content']['application/json'];

		self::assertSame(['$ref' => '#/components/schemas/PortfolioRequest'], $json['schema']);
		self::assertSame(['custom' => ['summary' => 'Ruční', 'value' => ['type' => 'Savings']]], $json['examples']);
	}

	#[Test]
	public function manualExampleWins(): void
	{
		$json = $this->spec()['paths']['/portfolios/manual-example']['post']['requestBody']['content']['application/json'];

		self::assertSame(['type' => 'Savings'], $json['example']);
		self::assertArrayNotHasKey('examples', $json);
	}

	#[Test]
	public function nonPolymorphicRequestBodyHasNoExamples(): void
	{
		$json = $this->spec()['paths']['/notes']['post']['requestBody']['content']['application/json'];

		self::assertSame(['schema' => ['$ref' => '#/components/schemas/NoteRequest']], $json);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function spec(): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('portfolio')->setType(PortfolioController::class);

		$schema = (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());
		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'Test API', version: '1.0.0')))->generate($schema);

		return json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
	}

}
