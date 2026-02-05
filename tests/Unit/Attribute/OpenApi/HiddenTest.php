<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Hidden;

/**
 * Tests for #[Hidden] attribute.
 */
final class HiddenTest extends TestCase
{

	#[Test]
	public function defaultReasonIsNull(): void
	{
		$hidden = new Hidden();

		self::assertNull($hidden->reason);
	}

	#[Test]
	public function reasonCanBeSet(): void
	{
		$hidden = new Hidden(reason: 'Internal endpoint for debugging');

		self::assertSame('Internal endpoint for debugging', $hidden->reason);
	}

	#[Test]
	public function attributeCanBeUsedOnMethod(): void
	{
		$reflectionMethod = new \ReflectionMethod(HiddenTestController::class, 'debugEndpoint');

		$attributes = $reflectionMethod->getAttributes(Hidden::class);

		self::assertCount(1, $attributes);

		/** @var Hidden $hidden */
		$hidden = $attributes[0]->newInstance();
		self::assertSame('Internal debug endpoint', $hidden->reason);
	}

	#[Test]
	public function attributeIsNotInherited(): void
	{
		$reflectionMethod = new \ReflectionMethod(HiddenTestController::class, 'publicEndpoint');

		$attributes = $reflectionMethod->getAttributes(Hidden::class);

		self::assertCount(0, $attributes);
	}

}

// Helper class for testing attribute on methods
class HiddenTestController
{

	#[Hidden(reason: 'Internal debug endpoint')]
	public function debugEndpoint(): void
	{
	}

	public function publicEndpoint(): void
	{
	}

}
