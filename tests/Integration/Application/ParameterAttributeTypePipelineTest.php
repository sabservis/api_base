<?php declare(strict_types = 1);

namespace Tests\Integration\Application;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\PathParameter;
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

final class ParameterAttributeTypePipelineTest extends TestCase
{

	#[Test]
	public function pathAttributeWithoutTypeInjectsSignatureType(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/items/42'),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"id":42}', $result->getBody());
	}

	#[Test]
	public function queryAttributeWithoutTypeInjectsSignatureType(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/items', queryParams: ['limit' => '5']),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"limit":5}', $result->getBody());
	}

	#[Test]
	public function missingQueryForNonNullableArgumentIsClientError(): void
	{
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);

		$this->createDispatcher()->dispatch(new ApiRequest(method: 'GET', uri: '/items'), new ApiResponse());
	}

	#[Test]
	public function missingQueryWithArgumentDefaultUsesDefault(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/items/page'),
			new ApiResponse(),
		);

		self::assertSame('{"page":1}', $result->getBody());
	}

	#[Test]
	public function missingQueryForNullableArgumentInjectsNull(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/items/search'),
			new ApiResponse(),
		);

		self::assertSame('{"q":null}', $result->getBody());
	}

	#[Test]
	public function explicitRequiredFalseIsKept(): void
	{
		$schema = $this->loadSchema();

		self::assertFalse($this->queryParameter($schema, '/items/explicit', 'limit')['required']);
		self::assertTrue($this->queryParameter($schema, '/items', 'limit')['required']);
	}

	/**
	 * @param array<mixed> $schema
	 * @return array<string, mixed>
	 */
	private function queryParameter(array $schema, string $mask, string $name): array
	{
		foreach ($schema['endpoints'] as $endpoint) {
			if (($endpoint['mask'] ?? null) === $mask) {
				return $endpoint['parameters'][$name];
			}
		}

		self::fail('Endpoint ' . $mask . ' not found');
	}

	/**
	 * @return array<mixed>
	 */
	private function loadSchema(): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('items.controller')
			->setType(UntypedParameterAttributeController::class);

		return (new OpenApiAttributeLoader($containerBuilder))->load();
	}

	private function createDispatcher(): ApiDispatcher
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('items.controller')
			->setType(UntypedParameterAttributeController::class);

		$schema = (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn(new UntypedParameterAttributeController());

		$serializer = new DataMapperSerializer();

		return new ApiDispatcher(
			new Router($schema),
			new ServiceHandler($container, $serializer),
			$serializer,
			new RequestParameterMapping(),
		);
	}

}

class UntypedParameterAttributeController implements Controller
{

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/items/{id}')]
	#[PathParameter(name: 'id', description: 'Item ID')]
	public function get(int $id): array
	{
		return ['id' => $id];
	}

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/items')]
	#[QueryParameter(name: 'limit', description: 'Max items')]
	public function list(int $limit): array
	{
		return ['limit' => $limit];
	}

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/items/page')]
	#[QueryParameter(name: 'page')]
	public function page(int $page = 1): array
	{
		return ['page' => $page];
	}

	/**
	 * @return array<string, string|null>
	 */
	#[Get(path: '/items/search')]
	#[QueryParameter(name: 'q')]
	public function search(string|null $q): array
	{
		return ['q' => $q];
	}

	/**
	 * @return array<string, int|null>
	 */
	#[Get(path: '/items/explicit')]
	#[QueryParameter(name: 'limit', required: false)]
	public function explicit(int|null $limit = null): array
	{
		return ['limit' => $limit];
	}

}
