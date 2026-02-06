<?php declare(strict_types = 1);

namespace Tests\Unit\Security;

use Nette\DI\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointAuthorization;
use Sabservis\Api\Security\AuthorizationChecker;
use Sabservis\Api\Security\Authorizer;
use stdClass;

final class AuthorizationCheckerTest extends TestCase
{

	#[Test]
	public function allowsRequestWhenAllAuthorizersAllow(): void
	{
		$authorizer = new AllowAllAuthorizer();
		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(AllowAllAuthorizer::class, false)
			->willReturn($authorizer);

		$checker = new AuthorizationChecker($container);
		$endpoint = new Endpoint('TestController', 'method');
		$endpoint->addAuthorization(new EndpointAuthorization('users.read', AllowAllAuthorizer::class));

		$request = new ApiRequest(method: 'GET', uri: '/users');
		$checker->authorize($request, $endpoint);

		self::assertCount(1, $authorizer->calls);
		self::assertSame('users.read', $authorizer->calls[0]['activity']);
	}

	#[Test]
	public function deniesRequestWhenAuthorizerReturnsFalse(): void
	{
		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(DenyAllAuthorizer::class, false)
			->willReturn(new DenyAllAuthorizer());

		$checker = new AuthorizationChecker($container);
		$endpoint = new Endpoint('TestController', 'method');
		$endpoint->addAuthorization(new EndpointAuthorization('users.delete', DenyAllAuthorizer::class));

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(403);
		$this->expectExceptionMessage('users.delete');

		$checker->authorize(new ApiRequest(method: 'DELETE', uri: '/users/1'), $endpoint);
	}

	#[Test]
	public function throwsWhenAuthorizerServiceIsMissing(): void
	{
		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(AllowAllAuthorizer::class, false)
			->willReturn(null);

		$checker = new AuthorizationChecker($container);
		$endpoint = new Endpoint('TestController', 'method');
		$endpoint->addAuthorization(new EndpointAuthorization('users.read', AllowAllAuthorizer::class));

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('not registered in DI');

		$checker->authorize(new ApiRequest(method: 'GET', uri: '/users'), $endpoint);
	}

	#[Test]
	public function throwsWhenServiceDoesNotImplementAuthorizer(): void
	{
		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->with(AllowAllAuthorizer::class, false)
			->willReturn(new stdClass());

		$checker = new AuthorizationChecker($container);
		$endpoint = new Endpoint('TestController', 'method');
		$endpoint->addAuthorization(new EndpointAuthorization('users.read', AllowAllAuthorizer::class));

		$this->expectException(InvalidStateException::class);
		$this->expectExceptionMessage('does not implement');

		$checker->authorize(new ApiRequest(method: 'GET', uri: '/users'), $endpoint);
	}

}

final class AllowAllAuthorizer implements Authorizer
{

	/** @var array<array{activity: string, endpoint: string}> */
	public array $calls = [];

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		$this->calls[] = [
			'activity' => $activity,
			'endpoint' => $endpoint->getControllerClass() . '::' . $endpoint->getControllerMethod(),
		];

		return true;
	}

}

final class DenyAllAuthorizer implements Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
	{
		return false;
	}

}
