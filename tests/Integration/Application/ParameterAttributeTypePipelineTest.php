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

}
