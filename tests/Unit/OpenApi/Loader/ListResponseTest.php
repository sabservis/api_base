<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\EndpointResponse;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;

final class ListResponseTest extends TestCase
{

	#[Test]
	public function listRefDefaultGeneratesDataWrappedResponseData(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListResponseController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];
		self::assertCount(3, $endpoints);

		// Find the list endpoint
		$listEndpoint = null;

		foreach ($endpoints as $endpoint) {
			if ($endpoint['methods'] === ['GET'] && $endpoint['mask'] === '/items') {
				$listEndpoint = $endpoint;

				break;
			}
		}

		self::assertNotNull($listEndpoint);
		self::assertArrayHasKey('responses', $listEndpoint);
		self::assertArrayHasKey('200', $listEndpoint['responses']);

		$response = $listEndpoint['responses']['200'];
		self::assertSame(ItemDto::class, $response['entity']);
		self::assertSame(EndpointResponse::WrapperListDataOnly, $response['wrapperType']);
	}

	#[Test]
	public function listRefWithMetaGeneratesCorrectResponseData(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListResponseController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];

		// Find the paginated endpoint
		$paginatedEndpoint = null;

		foreach ($endpoints as $endpoint) {
			if ($endpoint['methods'] === ['GET'] && $endpoint['mask'] === '/items/paginated') {
				$paginatedEndpoint = $endpoint;

				break;
			}
		}

		self::assertNotNull($paginatedEndpoint);
		self::assertArrayHasKey('responses', $paginatedEndpoint);
		self::assertArrayHasKey('200', $paginatedEndpoint['responses']);

		$response = $paginatedEndpoint['responses']['200'];
		self::assertSame(ItemDto::class, $response['entity']);
		self::assertSame(EndpointResponse::WrapperListWithMeta, $response['wrapperType']);
	}

	#[Test]
	public function listRefGeneratesCorrectOpenApiSpec(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListResponseController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		// Hydrate endpoints
		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		// Generate OpenAPI spec
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate($schema);

		$specArray = json_decode(json_encode($spec), true);

		// Check /items endpoint (default: data-wrapped)
		$itemsResponse = $specArray['paths']['/items']['get']['responses']['200'];
		self::assertArrayHasKey('content', $itemsResponse);

		$itemsSchema = $itemsResponse['content']['application/json']['schema'];
		self::assertSame('object', $itemsSchema['type']);
		self::assertArrayHasKey('data', $itemsSchema['properties']);
		self::assertArrayNotHasKey('meta', $itemsSchema['properties']);
		self::assertSame('array', $itemsSchema['properties']['data']['type']);
		self::assertSame('#/components/schemas/ItemDto', $itemsSchema['properties']['data']['items']['$ref']);
		self::assertSame(['data'], $itemsSchema['required']);

		// Check /items/paginated endpoint (list with meta)
		$paginatedResponse = $specArray['paths']['/items/paginated']['get']['responses']['200'];
		self::assertArrayHasKey('content', $paginatedResponse);

		$paginatedSchema = $paginatedResponse['content']['application/json']['schema'];
		self::assertSame('object', $paginatedSchema['type']);
		self::assertArrayHasKey('data', $paginatedSchema['properties']);
		self::assertArrayHasKey('meta', $paginatedSchema['properties']);

		// Data should be array of ItemDto
		self::assertSame('array', $paginatedSchema['properties']['data']['type']);
		self::assertSame('#/components/schemas/ItemDto', $paginatedSchema['properties']['data']['items']['$ref']);

		// Meta should have pagination fields
		$metaProps = $paginatedSchema['properties']['meta']['properties'];
		self::assertArrayHasKey('total', $metaProps);
		self::assertArrayHasKey('limit', $metaProps);
		self::assertArrayHasKey('offset', $metaProps);
	}

	#[Test]
	public function listRefUnwrappedGeneratesCorrectResponseData(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListResponseController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$endpoints = $result['endpoints'];

		// Find the unwrapped endpoint
		$unwrappedEndpoint = null;

		foreach ($endpoints as $endpoint) {
			if ($endpoint['methods'] === ['GET'] && $endpoint['mask'] === '/items/unwrapped') {
				$unwrappedEndpoint = $endpoint;

				break;
			}
		}

		self::assertNotNull($unwrappedEndpoint);
		self::assertArrayHasKey('responses', $unwrappedEndpoint);
		self::assertArrayHasKey('200', $unwrappedEndpoint['responses']);

		$response = $unwrappedEndpoint['responses']['200'];
		self::assertSame(ItemDto::class, $response['entity']);
		self::assertSame(EndpointResponse::WrapperListNoMeta, $response['wrapperType']);
	}

	#[Test]
	public function listRefUnwrappedGeneratesCorrectOpenApiSpec(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListResponseController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate($schema);

		$specArray = json_decode(json_encode($spec), true);

		// Check /items/unwrapped endpoint (plain array)
		$unwrappedResponse = $specArray['paths']['/items/unwrapped']['get']['responses']['200'];
		self::assertArrayHasKey('content', $unwrappedResponse);

		$unwrappedSchema = $unwrappedResponse['content']['application/json']['schema'];
		self::assertSame('array', $unwrappedSchema['type']);
		self::assertSame('#/components/schemas/ItemDto', $unwrappedSchema['items']['$ref']);
	}

	#[Test]
	public function listRefArrayDefaultGeneratesOneOfDataWrapped(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListOneOfController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate($schema);

		$specArray = json_decode(json_encode($spec), true);

		// Check /feed endpoint (default: data-only wrapper with oneOf)
		$feedResponse = $specArray['paths']['/feed']['get']['responses']['200'];
		self::assertArrayHasKey('content', $feedResponse);

		$feedSchema = $feedResponse['content']['application/json']['schema'];
		self::assertSame('object', $feedSchema['type']);
		self::assertArrayHasKey('data', $feedSchema['properties']);
		self::assertArrayNotHasKey('meta', $feedSchema['properties']);

		// Data should be array with oneOf items
		self::assertSame('array', $feedSchema['properties']['data']['type']);
		self::assertArrayHasKey('oneOf', $feedSchema['properties']['data']['items']);
		self::assertCount(2, $feedSchema['properties']['data']['items']['oneOf']);

		self::assertSame(['data'], $feedSchema['required']);

		// Check schemas are registered
		self::assertArrayHasKey('ArticleItemDto', $specArray['components']['schemas']);
		self::assertArrayHasKey('VideoItemDto', $specArray['components']['schemas']);
	}

	#[Test]
	public function listRefArrayUnwrappedGeneratesOneOfPlainArray(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListOneOfController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate($schema);

		$specArray = json_decode(json_encode($spec), true);

		// Check /feed/unwrapped endpoint (plain array with oneOf)
		$feedResponse = $specArray['paths']['/feed/unwrapped']['get']['responses']['200'];
		self::assertArrayHasKey('content', $feedResponse);

		$feedSchema = $feedResponse['content']['application/json']['schema'];
		self::assertSame('array', $feedSchema['type']);
		self::assertArrayHasKey('oneOf', $feedSchema['items']);
		self::assertCount(2, $feedSchema['items']['oneOf']);
	}

	#[Test]
	public function listRefArrayGeneratesOneOfWithMeta(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType(ListOneOfController::class);

		$loader = new OpenApiAttributeLoader($containerBuilder);
		$result = $loader->load();

		// Hydrate endpoints
		$hydrator = new ArrayHydrator();
		$schema = $hydrator->hydrate([
			'endpoints' => $result['endpoints'],
			'tags' => $result['tags'],
		]);

		// Generate OpenAPI spec
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);
		$spec = $generator->generate($schema);

		$specArray = json_decode(json_encode($spec), true);

		// Check /feed/paginated endpoint (list with meta, oneOf)
		$feedResponse = $specArray['paths']['/feed/paginated']['get']['responses']['200'];
		self::assertArrayHasKey('content', $feedResponse);

		$feedSchema = $feedResponse['content']['application/json']['schema'];
		self::assertSame('object', $feedSchema['type']);
		self::assertArrayHasKey('data', $feedSchema['properties']);
		self::assertArrayHasKey('meta', $feedSchema['properties']);

		// Data should be array with oneOf items
		self::assertSame('array', $feedSchema['properties']['data']['type']);
		self::assertArrayHasKey('oneOf', $feedSchema['properties']['data']['items']);
		self::assertCount(2, $feedSchema['properties']['data']['items']['oneOf']);

		// Meta should have pagination fields
		$metaProps = $feedSchema['properties']['meta']['properties'];
		self::assertArrayHasKey('total', $metaProps);
		self::assertArrayHasKey('limit', $metaProps);
		self::assertArrayHasKey('offset', $metaProps);
	}

}

// Test controllers and DTOs

class ListResponseController implements Controller
{

	#[Get(path: '/items')]
	#[Response(listRef: ItemDto::class)]
	public function list(): array
	{
		return [];
	}

	#[Get(path: '/items/paginated')]
	#[Response(listRef: ItemDto::class, withMeta: true)]
	public function listPaginated(): array
	{
		return [];
	}

	#[Get(path: '/items/unwrapped')]
	#[Response(listRef: ItemDto::class, unwrapped: true)]
	public function listUnwrapped(): array
	{
		return [];
	}

}

class ListOneOfController implements Controller
{

	#[Get(path: '/feed')]
	#[Response(listRef: [ArticleItemDto::class, VideoItemDto::class])]
	public function feed(): array
	{
		return [];
	}

	#[Get(path: '/feed/paginated')]
	#[Response(listRef: [ArticleItemDto::class, VideoItemDto::class], withMeta: true)]
	public function feedPaginated(): array
	{
		return [];
	}

	#[Get(path: '/feed/unwrapped')]
	#[Response(listRef: [ArticleItemDto::class, VideoItemDto::class], unwrapped: true)]
	public function feedUnwrapped(): array
	{
		return [];
	}

}

class ItemDto
{

	public int $id;

	public string $name;

}

class ArticleItemDto
{

	public int $id;

	public string $title;

}

class VideoItemDto
{

	public int $id;

	public string $url;

}
