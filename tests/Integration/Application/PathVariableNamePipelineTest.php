<?php declare(strict_types = 1);

namespace Tests\Integration\Application;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\PathParameter;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class PathVariableNamePipelineTest extends TestCase
{

	#[Test]
	public function hyphenatedPathVariableIsMatchedUnderOriginalName(): void
	{
		$matched = (new Router($this->schema()))->match(new ApiRequest(method: 'GET', uri: '/users/42/orders'));

		self::assertNotNull($matched);
		self::assertSame(['user-id' => '42'], $matched->getAttribute(RequestAttributes::PathParameters->value));
	}

	#[Test]
	public function hyphenatedPathVariableIsInjectedViaArgumentAttribute(): void
	{
		$result = $this->dispatch(new ApiRequest(method: 'GET', uri: '/users/42/orders'));

		self::assertSame('{"userId":42}', $result->getBody());
	}

	#[Test]
	public function pathVariableStartingWithDigitIsMatched(): void
	{
		$matched = (new Router($this->schema()))->match(new ApiRequest(method: 'GET', uri: '/codes/abc'));

		self::assertNotNull($matched);
		self::assertSame(['1code' => 'abc'], $matched->getAttribute(RequestAttributes::PathParameters->value));
	}

	#[Test]
	public function openApiKeepsOriginalVariableName(): void
	{
		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'T', version: '1')))->generate($this->schema());
		$data = json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('/users/{user-id}/orders', $data['paths']);
		self::assertSame('user-id', $data['paths']['/users/{user-id}/orders']['get']['parameters'][0]['name']);
	}

	#[Test]
	public function plainPathVariableIsUnchanged(): void
	{
		$matched = (new Router($this->schema()))->match(new ApiRequest(method: 'GET', uri: '/items/7'));

		self::assertNotNull($matched);
		self::assertSame(['id' => '7'], $matched->getAttribute(RequestAttributes::PathParameters->value));
	}

	private function schema(): Schema
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')->setType(PathVariableNameController::class);

		return (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());
	}

	private function dispatch(ApiRequest $request): ApiResponse
	{
		$container = $this->createMock(Container::class);
		$container->method('getByType')->willReturn(new PathVariableNameController());
		$serializer = new DataMapperSerializer();

		return (new ApiDispatcher(
			new Router($this->schema()),
			new ServiceHandler($container, $serializer),
			$serializer,
			new RequestParameterMapping(),
		))->dispatch($request, new ApiResponse());
	}

}

class PathVariableNameController implements Controller
{

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/users/{user-id}/orders')]
	public function orders(
		#[PathParameter(name: 'user-id')]
		int $userId,
	): array
	{
		return ['userId' => $userId];
	}

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/codes/{1code}')]
	#[PathParameter(name: '1code')]
	public function code(): array
	{
		return [];
	}

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/items/{id}')]
	public function item(int $id): array
	{
		return ['id' => $id];
	}

}
