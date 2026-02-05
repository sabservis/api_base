<?php declare(strict_types = 1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\ApiException;

final class ApiExceptionTest extends TestCase
{

	#[Test]
	public function defaultCodeIs400(): void
	{
		$exception = new ApiException('Test');

		self::assertSame(400, $exception->getCode());
	}

	#[Test]
	public function messageIsSet(): void
	{
		$exception = new ApiException('Test message');

		self::assertSame('Test message', $exception->getMessage());
	}

	#[Test]
	public function customCodeIsSet(): void
	{
		$exception = new ApiException('Test', 500);

		self::assertSame(500, $exception->getCode());
	}

	#[Test]
	public function previousExceptionIsSet(): void
	{
		$previous = new \RuntimeException('Original');
		$exception = new ApiException('Wrapped', 400, $previous);

		self::assertSame($previous, $exception->getPrevious());
	}

	#[Test]
	public function contextIsSetInConstructor(): void
	{
		$context = ['key' => 'value', 'nested' => ['a' => 1]];
		$exception = new ApiException('Test', 400, null, $context);

		self::assertSame($context, $exception->getContext());
	}

	#[Test]
	public function contextIsNullByDefault(): void
	{
		$exception = new ApiException('Test');

		self::assertNull($exception->getContext());
	}

	#[Test]
	public function createReturnsNewInstance(): void
	{
		$exception = ApiException::create();

		self::assertInstanceOf(ApiException::class, $exception);
	}

	#[Test]
	public function withMessageSetsMessage(): void
	{
		$exception = ApiException::create()
			->withMessage('New message');

		self::assertSame('New message', $exception->getMessage());
	}

	#[Test]
	public function withContextSetsContext(): void
	{
		$context = ['field' => 'email'];
		$exception = ApiException::create()
			->withContext($context);

		self::assertSame($context, $exception->getContext());
	}

	#[Test]
	public function withTypedContextSetsTypedContext(): void
	{
		$exception = ApiException::create()
			->withTypedContext('validation', ['email' => 'Invalid']);

		self::assertSame(['validation' => ['email' => 'Invalid']], $exception->getContext());
	}

	#[Test]
	public function withContextOverwritesPrevious(): void
	{
		$exception = new ApiException('Test', 400, null, ['original' => true]);
		$exception->withContext(['new' => true]);

		self::assertSame(['new' => true], $exception->getContext());
	}

	#[Test]
	public function extendsRuntimeException(): void
	{
		$exception = new ApiException('Test');

		self::assertInstanceOf(\RuntimeException::class, $exception);
	}

	#[Test]
	public function fluentInterfaceReturnsStatic(): void
	{
		$exception = ApiException::create();
		$result = $exception->withMessage('Test');

		self::assertSame($exception, $result);
	}

}
