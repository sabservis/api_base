<?php declare(strict_types = 1);

namespace Tests\Integration\Application;

use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Put;
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

final class NullableParameterPipelineTest extends TestCase
{

	#[Test]
	public function missingNullableQueryParameterWithoutDefaultInjectsNull(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/nullable'),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"limit":null}', $result->getBody());
	}

	#[Test]
	public function providedNullableQueryParameterIsInjected(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/nullable', queryParams: ['limit' => '5']),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"limit":5}', $result->getBody());
	}

	#[Test]
	public function missingNullableBodyDtoWithoutDefaultInjectsNull(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'PUT', uri: '/nullable/7'),
			new ApiResponse(),
		);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('{"id":7,"name":null}', $result->getBody());
	}

	#[Test]
	public function missingRequiredQueryParameterIsStillRejected(): void
	{
		try {
			$this->createDispatcher()->dispatch(
				new ApiRequest(method: 'GET', uri: '/required'),
				new ApiResponse(),
			);
			self::fail('Expected missing required parameter to be rejected');
		} catch (ClientErrorException $e) {
			self::assertSame(400, $e->getCode());
		}
	}

	private function createDispatcher(): ApiDispatcher
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('nullable.controller')
			->setType(NullableParameterController::class);

		$schema = (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());

		$container = $this->createMock(Container::class);
		$container->method('getByType')
			->willReturn(new NullableParameterController());

		$serializer = new DataMapperSerializer();

		return new ApiDispatcher(
			new Router($schema),
			new ServiceHandler($container, $serializer),
			$serializer,
			new RequestParameterMapping(),
		);
	}

}

class NullableParameterController implements Controller
{

	/**
	 * @return array<string, int|null>
	 */
	#[Get(path: '/nullable')]
	public function list(int|null $limit): array
	{
		return ['limit' => $limit];
	}

	/**
	 * @return array<string, int>
	 */
	#[Get(path: '/required')]
	public function required(int $page): array
	{
		return ['page' => $page];
	}

	/**
	 * @return array<string, int|string|null>
	 */
	#[Put(path: '/nullable/{id}')]
	public function update(int $id, NullableParameterDto|null $input): array
	{
		return ['id' => $id, 'name' => $input?->name];
	}

}

class NullableParameterDto
{

	public string $name;

}
