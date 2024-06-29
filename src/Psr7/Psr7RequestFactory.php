<?php declare(strict_types = 1);

namespace Sabservis\Api\Psr7;

use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use function str_replace;

class Psr7RequestFactory
{

	public static function fromGlobal(): Psr7Request
	{
		return self::fromLaminas(ServerRequestFactory::fromGlobals());
	}

	public static function fromLaminas(ServerRequestInterface $request): Psr7Request
	{
		return new Psr7Request(
			$request->getMethod(),
			$request->getUri(),
			$request->getHeaders(),
			$request->getBody(),
			str_replace('HTTP/', '', $request->getHeaderLine('SERVER_PROTOCOL')),
		);
	}

}
