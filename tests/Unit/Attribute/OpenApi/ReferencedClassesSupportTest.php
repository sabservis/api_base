<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\ReferencedClassesSupport;

final class ReferencedClassesSupportTest extends TestCase
{

	#[Test]
	public function extractRefClassesReturnsClassForFqn(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractRefClasses(RefTestClassA::class);

		self::assertContains(RefTestClassA::class, $result);
		self::assertCount(1, $result);
	}

	#[Test]
	public function extractRefClassesReturnsEmptyForSimpleString(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractRefClasses('SimpleRef');

		self::assertSame([], $result);
	}

	#[Test]
	public function extractRefClassesReturnsEmptyForNull(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractRefClasses(null);

		self::assertSame([], $result);
	}

	#[Test]
	public function extractCompositionClassesExtractsFromOneOf(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractCompositionClasses(
			oneOf: [RefTestClassA::class, RefTestClassB::class],
		);

		self::assertContains(RefTestClassA::class, $result);
		self::assertContains(RefTestClassB::class, $result);
		self::assertCount(2, $result);
	}

	#[Test]
	public function extractCompositionClassesExtractsFromAnyOf(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractCompositionClasses(
			anyOf: [RefTestClassA::class],
		);

		self::assertContains(RefTestClassA::class, $result);
	}

	#[Test]
	public function extractCompositionClassesExtractsFromAllOf(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractCompositionClasses(
			allOf: [RefTestClassA::class, RefTestClassB::class],
		);

		self::assertContains(RefTestClassA::class, $result);
		self::assertContains(RefTestClassB::class, $result);
	}

	#[Test]
	public function extractCompositionClassesIgnoresNonClassStrings(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractCompositionClasses(
			oneOf: [RefTestClassA::class, 'string', 'integer'],
		);

		self::assertContains(RefTestClassA::class, $result);
		self::assertNotContains('string', $result);
		self::assertNotContains('integer', $result);
		self::assertCount(1, $result);
	}

	#[Test]
	public function extractCompositionClassesCombinesAll(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractCompositionClasses(
			oneOf: [RefTestClassA::class],
			anyOf: [RefTestClassB::class],
			allOf: [RefTestClassC::class],
		);

		self::assertContains(RefTestClassA::class, $result);
		self::assertContains(RefTestClassB::class, $result);
		self::assertContains(RefTestClassC::class, $result);
		self::assertCount(3, $result);
	}

	#[Test]
	public function extractItemsClassesDelegatesToItems(): void
	{
		$helper = $this->createHelper();
		$items = new Items(ref: RefTestClassA::class);

		$result = $helper->testExtractItemsClasses($items);

		self::assertContains(RefTestClassA::class, $result);
	}

	#[Test]
	public function extractItemsClassesReturnsEmptyForNull(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractItemsClasses(null);

		self::assertSame([], $result);
	}

	#[Test]
	public function extractPropertiesClassesDelegatesToProperties(): void
	{
		$helper = $this->createHelper();
		$properties = [
			new Property(property: 'a', ref: RefTestClassA::class),
			new Property(property: 'b', ref: RefTestClassB::class),
		];

		$result = $helper->testExtractPropertiesClasses($properties);

		self::assertContains(RefTestClassA::class, $result);
		self::assertContains(RefTestClassB::class, $result);
	}

	#[Test]
	public function extractPropertiesClassesReturnsEmptyForNull(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testExtractPropertiesClasses(null);

		self::assertSame([], $result);
	}

	/**
	 * @return object
	 */
	private function createHelper(): object
	{
		return new class {

			use ReferencedClassesSupport;

			/**
			 * @return array<class-string>
			 */
			public function testExtractRefClasses(string|null $ref): array
			{
				return $this->extractRefClasses($ref);
			}

			/**
			 * @param array<mixed>|null $oneOf
			 * @param array<mixed>|null $anyOf
			 * @param array<mixed>|null $allOf
			 * @return array<class-string>
			 */
			public function testExtractCompositionClasses(
				array|null $oneOf = null,
				array|null $anyOf = null,
				array|null $allOf = null,
			): array
			{
				return $this->extractCompositionClasses($oneOf, $anyOf, $allOf);
			}

			/**
			 * @return array<class-string>
			 */
			public function testExtractItemsClasses(Items|null $items): array
			{
				return $this->extractItemsClasses($items);
			}

			/**
			 * @param array<Property>|null $properties
			 * @return array<class-string>
			 */
			public function testExtractPropertiesClasses(array|null $properties): array
			{
				return $this->extractPropertiesClasses($properties);
			}

		};
	}

}

class RefTestClassA
{

	public string $name;

}

class RefTestClassB
{

	public int $id;

}

class RefTestClassC
{

	public bool $active;

}
