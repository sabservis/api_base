<?php declare(strict_types = 1);

namespace Tests\Integration\Application;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\CookieParameter;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\HeaderParameter;
use Sabservis\Api\Attribute\OpenApi\QueryParameter;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;

final class HeaderCookieParameterPipelineTest extends TestCase
{

	#[Test]
	public function cookieParameterIsInjectedByName(): void
	{
		$result = $this->dispatch(new ApiRequest(method: 'GET', uri: '/cookie', cookies: ['session' => 'abc']));

		self::assertSame('{"session":"abc"}', $result->getBody());
	}

	#[Test]
	public function headerParameterIsInjectedByName(): void
	{
		$result = $this->dispatch(new ApiRequest(method: 'GET', uri: '/header', headers: ['Tenant' => 'acme']));

		self::assertSame('{"tenant":"acme"}', $result->getBody());
	}

	#[Test]
	public function argumentAttributeBindsHeaderAndCookieWithDifferentNames(): void
	{
		$result = $this->dispatch(new ApiRequest(
			method: 'GET',
			uri: '/api-key',
			headers: ['X-API-Key' => 'secret'],
			cookies: ['session' => 'abc'],
		));

		self::assertSame('{"apiKey":"secret","sid":"abc"}', $result->getBody());
	}

	#[Test]
	public function argumentAttributeBindsQueryWithDifferentNameAndSignatureType(): void
	{
		$result = $this->dispatch(new ApiRequest(method: 'GET', uri: '/page', queryParams: ['page-size' => '25']));

		self::assertSame('{"pageSize":25}', $result->getBody());
	}

	#[Test]
	public function missingRequiredHeaderArgumentIsRejectedWith400(): void
	{
		try {
			$this->dispatch(new ApiRequest(method: 'GET', uri: '/api-key', cookies: ['session' => 'abc']));
			self::fail('Expected missing header to be rejected');
		} catch (ClientErrorException $e) {
			self::assertSame(400, $e->getCode());
		}
	}

	#[Test]
	public function missingOptionalHeaderArgumentInjectsNull(): void
	{
		$result = $this->dispatch(new ApiRequest(method: 'GET', uri: '/optional-header'));

		self::assertSame('{"traceId":null}', $result->getBody());
	}

	#[Test]
	public function argumentAttributeDefinesOpenApiParameterWithoutInferredQuery(): void
	{
		$endpoint = $this->loadEndpoint('apiKey');

		self::assertArrayNotHasKey('apiKey', $endpoint['parameters']);
		self::assertArrayNotHasKey('sid', $endpoint['parameters']);
		self::assertSame('header', $endpoint['parameters']['X-API-Key']['in']);
		self::assertTrue($endpoint['parameters']['X-API-Key']['required']);
		self::assertSame('cookie', $endpoint['parameters']['session']['in']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function loadEndpoint(string $method): array
	{
		foreach ($this->loadSchema()['endpoints'] as $endpoint) {
			if ($endpoint['handler']['method'] === $method) {
				return $endpoint;
			}
		}

		self::fail('Endpoint not found: ' . $method);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function loadSchema(): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('headerCookie.controller')
			->setType(HeaderCookieParameterController::class);

		return (new OpenApiAttributeLoader($containerBuilder))->load();
	}

	private function dispatch(ApiRequest $request): ApiResponse
	{
		$schema = (new ArrayHydrator())->hydrate($this->loadSchema());

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn(new HeaderCookieParameterController());

		$serializer = new DataMapperSerializer();
		$dispatcher = new ApiDispatcher(
			new Router($schema),
			new ServiceHandler($container, $serializer),
			$serializer,
			new RequestParameterMapping(),
		);

		return $dispatcher->dispatch($request, new ApiResponse());
	}

}

class HeaderCookieParameterController implements Controller
{

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/cookie')]
	#[CookieParameter(name: 'session')]
	public function cookie(string $session): array
	{
		return ['session' => $session];
	}

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/header')]
	#[HeaderParameter(name: 'tenant')]
	public function header(string $tenant): array
	{
		return ['tenant' => $tenant];
	}

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/api-key')]
	public function apiKey(
		#[HeaderParameter(name: 'X-API-Key')]
		string $apiKey,
		#[CookieParameter(name: 'session')]
		string $sid,
	): array
	{
		return ['apiKey' => $apiKey, 'sid' => $sid];
	}

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/page')]
	public function page(
		#[QueryParameter(name: 'page-size')]
		int $pageSize,
	): array
	{
		return ['pageSize' => $pageSize];
	}

	/**
	 * @return array<string, string|null>
	 */
	#[Get(path: '/optional-header')]
	public function optionalHeader(
		#[HeaderParameter(name: 'X-Trace-Id')]
		string|null $traceId,
	): array
	{
		return ['traceId' => $traceId];
	}

}
