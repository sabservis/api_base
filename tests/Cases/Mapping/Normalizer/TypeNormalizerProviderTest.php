<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer;

require_once __DIR__ . '/../../../bootstrap.php';

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Sabservis\Api\Mapping\Entity\Entity;
use Sabservis\Api\Mapping\Normalizer\TypeNormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Types;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use UnitEnum;

Environment::setupFunctions();

final class TypeNormalizerProviderTest extends TestCase
{

	public function testAddNormalizer(): void
	{
		$provider = new TypeNormalizerProvider();
		Assert::count(0, $provider->normalizers);
		Assert::null($provider->findNormalizer('test'));

		$provider->addNormalizer(Types\ArrayTypeNormalizer::class);
		Assert::count(1, $provider->normalizers);
		Assert::type(Types\ArrayTypeNormalizer::class, $provider->findNormalizer('array'));

		$provider->addNormalizer(Types\IntegerTypeNormalizer::class);
		Assert::count(2, $provider->normalizers);
		Assert::type(Types\IntegerTypeNormalizer::class, $provider->findNormalizer('int'));
		Assert::type(Types\IntegerTypeNormalizer::class, $provider->findNormalizer('integer'));
	}

	public function testFindNormalizer(): void
	{
		$normalizers = [
			Types\ArrayTypeNormalizer::class,
			Types\BackedEnumTypeNormalizer::class,
			Types\BooleanTypeNormalizer::class,
			Types\DateTimeNormalizer::class,
			Types\FloatTypeNormalizer::class,
			Types\IntegerTypeNormalizer::class,
			Types\ObjectTypeNormalizer::class,
			Types\StringTypeNormalizer::class,
			Types\UnitEnumTypeNormalizer::class,
		];

		$provider = new TypeNormalizerProvider();

		foreach ($normalizers as $normalizer) {
			$provider->addNormalizer($normalizer);
		}

		Assert::type(Types\ArrayTypeNormalizer::class, $provider->findNormalizer('array'));

		Assert::type(Types\BackedEnumTypeNormalizer::class, $provider->findNormalizer(BackedEnum::class));

		Assert::type(Types\BooleanTypeNormalizer::class, $provider->findNormalizer('bool'));
		Assert::type(Types\BooleanTypeNormalizer::class, $provider->findNormalizer('boolean'));

		Assert::type(Types\DateTimeNormalizer::class, $provider->findNormalizer('date'));
		Assert::type(Types\DateTimeNormalizer::class, $provider->findNormalizer(DateTimeImmutable::class));
		Assert::type(Types\DateTimeNormalizer::class, $provider->findNormalizer(DateTime::class));
		Assert::type(Types\DateTimeNormalizer::class, $provider->findNormalizer(DateTimeInterface::class));

		Assert::type(Types\FloatTypeNormalizer::class, $provider->findNormalizer('float'));
		Assert::type(Types\FloatTypeNormalizer::class, $provider->findNormalizer('double'));
		Assert::type(Types\FloatTypeNormalizer::class, $provider->findNormalizer('decimal'));
		Assert::type(Types\FloatTypeNormalizer::class, $provider->findNormalizer('number'));

		Assert::type(Types\IntegerTypeNormalizer::class, $provider->findNormalizer('int'));
		Assert::type(Types\IntegerTypeNormalizer::class, $provider->findNormalizer('integer'));

		Assert::type(Types\ObjectTypeNormalizer::class, $provider->findNormalizer('object'));
		Assert::type(Types\ObjectTypeNormalizer::class, $provider->findNormalizer(Entity::class));

		Assert::type(Types\StringTypeNormalizer::class, $provider->findNormalizer('string'));
		Assert::type(Types\StringTypeNormalizer::class, $provider->findNormalizer('text'));

		Assert::type(Types\UnitEnumTypeNormalizer::class, $provider->findNormalizer(UnitEnum::class));
	}

}

$test = new TypeNormalizerProviderTest();
$test->run();
