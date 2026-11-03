<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Discriminator;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\OpenApi\Generator\ExampleBuilder;
use Sabservis\Api\OpenApi\Generator\SchemaBuilder;
use Sabservis\Api\OpenApi\Spec\DiscriminatorObject;
use Sabservis\Api\OpenApi\Spec\SchemaObject;
use function array_keys;

final class ExampleBuilderTest extends TestCase
{

	#[Test]
	public function buildsOneExamplePerMappingKeyInMappingOrder(): void
	{
		$examples = $this->examplesFor(ExBase::class);

		self::assertNotNull($examples);
		self::assertSame(['a', 'b'], array_keys($examples));
	}

	#[Test]
	public function variantValueFollowsRules(): void
	{
		$examples = $this->examplesFor(ExBase::class);

		self::assertSame(
			[
				'summary' => 'Varianta A',
				'description' => 'Popis varianty A',
				'value' => [
					// discriminator first, then schema order
					'type' => 'a',
					'name' => 'Jan',
					'currency' => 'CZK',
					'address' => ['street' => 'Main 1'],
					'items' => [['sku' => 'X-1']],
					'payment' => ['kind' => 'card', 'cardNumber' => '4111'],
				],
			],
			$examples['a'] ?? null,
		);
	}

	#[Test]
	public function discriminatorValueOverridesPropertyExampleAndSummaryFallsBackToSchemaName(): void
	{
		$examples = $this->examplesFor(ExBase::class);

		self::assertSame('ExVariantB', $examples['b']['summary'] ?? null);
		self::assertArrayNotHasKey('description', $examples['b'] ?? []);
		self::assertSame('b', $examples['b']['value']['type'] ?? null);
		self::assertSame(100, $examples['b']['value']['amount'] ?? null);
	}

	#[Test]
	public function nestedObjectsAreLimitedToThreeLevels(): void
	{
		$examples = $this->examplesFor(ExBase::class);

		self::assertSame(
			['value' => 1, 'next' => ['value' => 2, 'next' => ['value' => 3]]],
			$examples['b']['value']['level1'] ?? null,
		);
	}

	#[Test]
	public function discriminatorPropertyMissingInVariantIsStillAdded(): void
	{
		$schemas = [
			'Root' => new SchemaObject(
				oneOf: [new SchemaObject(ref: 'Only')],
				discriminator: new DiscriminatorObject('kind', ['only' => '#/components/schemas/Only']),
			),
			'Only' => new SchemaObject(
				type: 'object',
				properties: ['name' => new SchemaObject(type: 'string', example: 'x')],
			),
		];

		$examples = (new ExampleBuilder($schemas))->buildDiscriminatorExamples('Root');

		self::assertSame(
			['only' => ['summary' => 'Only', 'value' => ['kind' => 'only', 'name' => 'x']]],
			$examples,
		);
	}

	#[Test]
	public function integerDiscriminatorKeepsIntegerType(): void
	{
		$schemas = [
			'Root' => new SchemaObject(
				oneOf: [new SchemaObject(ref: 'One')],
				discriminator: new DiscriminatorObject('version', [1 => '#/components/schemas/One']),
			),
			'One' => new SchemaObject(
				type: 'object',
				properties: ['version' => new SchemaObject(type: 'integer', enum: [1])],
			),
		];

		$examples = (new ExampleBuilder($schemas))->buildDiscriminatorExamples('Root');

		self::assertSame(1, $examples['1']['value']['version'] ?? null);
	}

	#[Test]
	public function oneOfWithoutDiscriminatorUsesFirstVariant(): void
	{
		$schemas = [
			'Root' => new SchemaObject(
				oneOf: [new SchemaObject(ref: 'A')],
				discriminator: new DiscriminatorObject('type', ['a' => '#/components/schemas/A']),
			),
			'A' => new SchemaObject(
				type: 'object',
				properties: [
					'value' => new SchemaObject(oneOf: [
						new SchemaObject(type: 'string', example: 'first'),
						new SchemaObject(type: 'integer', example: 2),
					]),
				],
			),
		];

		$examples = (new ExampleBuilder($schemas))->buildDiscriminatorExamples('Root');

		self::assertSame(['type' => 'a', 'value' => 'first'], $examples['a']['value'] ?? null);
	}

	#[Test]
	public function nonPolymorphicSchemaHasNoExamples(): void
	{
		self::assertNull($this->examplesFor(ExAddress::class));
		self::assertNull((new ExampleBuilder([]))->buildDiscriminatorExamples('Missing'));
	}

	/**
	 * @param class-string $class
	 * @return array<string, mixed>|null
	 */
	private function examplesFor(string $class): array|null
	{
		$builder = new SchemaBuilder();
		$name = $builder->registerClass($class);

		return (new ExampleBuilder($builder->getSchemas()))->buildDiscriminatorExamples($name);
	}

}

#[Schema(oneOf: [ExVariantA::class, ExVariantB::class])]
#[Discriminator(propertyName: 'type', mapping: ['a' => ExVariantA::class, 'b' => ExVariantB::class])]
class ExBase
{

}

#[Schema(title: 'Varianta A', description: 'Popis varianty A')]
class ExVariantA
{

	#[Property(example: 'Jan')]
	public string $name;

	public string $type;

	#[Property(enum: ['CZK'])]
	public string $currency;

	public ExAddress $address;

	/** @var list<ExItem> */
	#[Property(type: 'array', items: new Items(ref: ExItem::class))]
	public array $items;

	/** @var list<ExEmpty> */
	#[Property(type: 'array', items: new Items(ref: ExEmpty::class))]
	public array $emptyItems = [];

	public string|null $note = null;

	#[Property(example: 5, readOnly: true)]
	public int $id;

	#[Property(example: 'old', deprecated: true)]
	public string $legacy;

	public self|null $self = null;

	public ExPayment $payment;

}

class ExVariantB
{

	#[Property(example: 'wrong')]
	public string $type;

	#[Property(example: 100)]
	public int $amount;

	public ExLevel1 $level1;

}

class ExAddress
{

	#[Property(example: 'Main 1')]
	public string $street;

	public string $city;

}

class ExItem
{

	#[Property(example: 'X-1')]
	public string $sku;

}

class ExEmpty
{

	public string $nothing;

}

#[Schema(oneOf: [ExCardPayment::class, ExBankPayment::class])]
#[Discriminator(propertyName: 'kind', mapping: ['card' => ExCardPayment::class, 'bank' => ExBankPayment::class])]
class ExPayment
{

}

class ExCardPayment
{

	public string $kind;

	#[Property(example: '4111')]
	public string $cardNumber;

}

class ExBankPayment
{

	public string $kind;

	#[Property(example: 'CZ65')]
	public string $iban;

}

class ExLevel1
{

	#[Property(example: 1)]
	public int $value;

	public ExLevel2 $next;

}

class ExLevel2
{

	#[Property(example: 2)]
	public int $value;

	public ExLevel3 $next;

}

class ExLevel3
{

	#[Property(example: 3)]
	public int $value;

	public ExLevel4 $next;

}

class ExLevel4
{

	#[Property(example: 4)]
	public int $value;

}
