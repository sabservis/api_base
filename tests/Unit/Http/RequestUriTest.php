<?php declare(strict_types = 1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Http\RequestUri;

final class RequestUriTest extends TestCase
{

	#[Test]
	public function parsesAllUriParts(): void
	{
		$uri = new RequestUri('https://api.example.com:8443/v1/users?page=2#details');

		self::assertSame('https', $uri->getScheme());
		self::assertSame('api.example.com', $uri->getHost());
		self::assertSame(8_443, $uri->getPort());
		self::assertSame('/v1/users', $uri->getPath());
		self::assertSame('page=2', $uri->getQuery());
		self::assertSame('details', $uri->getFragment());
		self::assertSame('https://api.example.com:8443/v1/users?page=2#details', (string) $uri);
	}

	#[Test]
	public function returnsFallbackValuesForRelativeUri(): void
	{
		$uri = new RequestUri('/v1/users');

		self::assertSame('', $uri->getScheme());
		self::assertSame('', $uri->getHost());
		self::assertNull($uri->getPort());
		self::assertSame('/v1/users', $uri->getPath());
		self::assertSame('', $uri->getQuery());
		self::assertSame('', $uri->getFragment());
	}

	#[Test]
	public function defaultsPathToRootWhenAbsoluteUriHasNoPath(): void
	{
		$uri = new RequestUri('https://api.example.com');

		self::assertSame('/', $uri->getPath());
	}

}
