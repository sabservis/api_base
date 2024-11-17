<?php declare(strict_types = 1);

namespace Tests\Cases\Mapping\Normalizer\Types;

require_once __DIR__ . '/../../../../bootstrap.php';

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Sabservis\Api\Exception\Runtime\InvalidArgumentTypeException;
use Sabservis\Api\Mapping\Normalizer\Types\DateTimeNormalizer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class DateTimeNormalizerTest extends TestCase
{

	private DateTimeNormalizer $normalizer;

	public function testDenormalize(): void
	{
		Assert::same(
			'2021-01-01 00:00:00',
			$this->normalizer->denormalize('2021-01-01 00:00:00')->format('Y-m-d H:i:s'),
		);
		Assert::same(
			'2021-01-01 00:00:00',
			$this->normalizer->denormalize('2021-01-01 00:00:00', 'Y-m-d H:i:s')->format('Y-m-d H:i:s'),
		);
		Assert::same('2021-01-01', $this->normalizer->denormalize('2021-01-01', 'Y-m-d')->format('Y-m-d'));
		Assert::same('2021-01-01 00:00:00', $this->normalizer->denormalize(1_609_459_200, 'U')->format('Y-m-d H:i:s'));
		Assert::same(
			'2021-01-01 00:00:00',
			$this->normalizer->denormalize(1_609_459_200.0, 'U')->format('Y-m-d H:i:s'),
		);
		Assert::same(
			'2021-01-01 00:00:00.000000',
			$this->normalizer->denormalize(1_609_459_200.0, 'U.u')->format('Y-m-d H:i:s.u'),
		);
		Assert::same(
			'2021-01-01 14:00:00-06:00',
			$this->normalizer->denormalize(
				'2021-01-01 14:00:00',
				'Y-m-d H:i:s',
				new DateTimeZone('America/Chicago'),
			)->format(
				'Y-m-d H:i:sP',
			),
		);
		Assert::same(
			'2021-01-01 14:00:00-06:00',
			$this->normalizer->denormalize('2021-01-01 14:00:00', 'Y-m-d H:i:s', 'America/Chicago')->format(
				'Y-m-d H:i:sP',
			),
		);

		Assert::exception(function (): void {
			$this->normalizer->denormalize('');
		}, InvalidArgumentTypeException::class, 'The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string');
	}

	public function testNormalize(): void
	{
		Assert::same('2021-01-01 00:00:00', $this->normalizer->normalize(new DateTimeImmutable('2021-01-01 00:00:00')));
		Assert::same(
			'2021-01-01 00:00:00',
			$this->normalizer->normalize(new DateTimeImmutable('2021-01-01 00:00:00'), 'Y-m-d H:i:s'),
		);
		Assert::same('2021-01-01', $this->normalizer->normalize(new DateTimeImmutable('2021-01-01'), 'Y-m-d'));
		Assert::same('1609455600', $this->normalizer->normalize(new DateTimeImmutable('2021-01-01 00:00:00'), 'U'));
		Assert::same(
			'1609455600.000000',
			$this->normalizer->normalize(new DateTimeImmutable('2021-01-01 00:00:00'), 'U.u'),
		);
		Assert::same(
			'2021-01-01 21:00:00',
			$this->normalizer->normalize(
				new DateTimeImmutable('2021-01-01 14:00:00', new DateTimeZone('America/Chicago')),
				'Y-m-d H:i:s',
			),
		);
		Assert::same(
			'2021-01-01 07:00:00',
			$this->normalizer->normalize(
				new DateTimeImmutable('2021-01-01 14:00:00', new DateTimeZone('Europe/Prague')),
				'Y-m-d H:i:s',
				'America/Chicago',
			),
		);

		Assert::exception(function (): void {
			$this->normalizer->normalize('');
		}, InvalidArgumentTypeException::class, 'The data is not an instance of DateTimeInterface');
	}

	public function testGetSupportedTypes(): void
	{
		Assert::same(
			[DateTimeImmutable::class, DateTime::class, DateTimeInterface::class, 'date'],
			DateTimeNormalizer::getSupportedTypes(),
		);
	}

	public function setUp(): void
	{
		parent::setUp();

		$this->normalizer = new DateTimeNormalizer();
	}

}

$test = new DateTimeNormalizerTest();
$test->run();
