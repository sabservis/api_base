<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Hidden;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Http\UploadedFile;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\Loader\RequestBodyBuilder;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function array_column;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

/**
 * #[Hidden] hides endpoints (and DTO properties) from OpenAPI, endpoints stay routable.
 */
final class HiddenEndpointTest extends TestCase
{

	#[Test]
	public function hiddenEndpointIsRoutable(): void
	{
		$router = new Router($this->hydrate(HiddenEndpointController::class));

		$matched = $router->match(new ApiRequest(method: 'GET', uri: '/api/debug'));

		self::assertInstanceOf(ApiRequest::class, $matched);
		$endpoint = $matched->getAttribute(RequestAttributes::Endpoint->value);
		self::assertInstanceOf(Endpoint::class, $endpoint);
		self::assertSame('hiddenEndpoint', $endpoint->getControllerMethod());
	}

	#[Test]
	public function hiddenEndpointIsExcludedFromOpenApi(): void
	{
		$paths = $this->generate(HiddenEndpointController::class)['paths'];

		self::assertArrayHasKey('/api/public', $paths);
		self::assertArrayNotHasKey('/api/debug', $paths);
	}

	#[Test]
	public function multipleEndpointsWithOneHidden(): void
	{
		$paths = $this->generate(MultipleEndpointsController::class)['paths'];

		self::assertArrayHasKey('/api/users', $paths);
		self::assertArrayHasKey('/api/users/{id}', $paths);
		self::assertArrayNotHasKey('/api/debug', $paths);

		$router = new Router($this->hydrate(MultipleEndpointsController::class));
		self::assertNotNull($router->match(new ApiRequest(method: 'GET', uri: '/api/debug')));
	}

	#[Test]
	public function allHiddenEndpointsAreExcludedFromOpenApi(): void
	{
		$spec = $this->generate(AllHiddenController::class);

		self::assertSame([], $spec['paths'] ?? []);
		self::assertCount(2, $this->hydrate(AllHiddenController::class)->getEndpoints());
	}

	#[Test]
	public function loaderMarksHiddenEndpoint(): void
	{
		$endpoints = $this->load(HiddenEndpointController::class)['endpoints'];
		$hidden = array_column($endpoints, 'hidden', 'mask');

		self::assertTrue($hidden['/api/debug']);
		self::assertFalse($hidden['/api/public']);
	}

	#[Test]
	public function hiddenDtoPropertyIsExcludedFromRequestBodySchema(): void
	{
		$spec = $this->generate(HiddenPropertyController::class);
		$schema = $spec['components']['schemas']['HiddenPropertyDto'];

		self::assertArrayHasKey('name', $schema['properties']);
		self::assertArrayNotHasKey('internalNote', $schema['properties']);
		self::assertNotContains('internalNote', $schema['required'] ?? []);
	}

	#[Test]
	public function hiddenDtoPropertyIsExcludedFromMultipartSchema(): void
	{
		$result = (new RequestBodyBuilder())->buildMultipartRequestBody(HiddenPropertyMultipartDto::class);
		$schema = $result['contentSpec']['multipart/form-data']['schema'];

		self::assertArrayHasKey('title', $schema['properties']);
		self::assertArrayHasKey('file', $schema['properties']);
		self::assertArrayNotHasKey('internalNote', $schema['properties']);
		self::assertNotContains('internalNote', $schema['required'] ?? []);
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<string, mixed>
	 */
	private function load(string $controllerClass): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')
			->setType($controllerClass);

		return (new OpenApiAttributeLoader($containerBuilder))->load();
	}

	/**
	 * @param class-string $controllerClass
	 */
	private function hydrate(string $controllerClass): Schema
	{
		return (new ArrayHydrator())->hydrate($this->load($controllerClass));
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<string, mixed>
	 */
	private function generate(string $controllerClass): array
	{
		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'Test API', version: '1.0.0')))
			->generate($this->hydrate($controllerClass));

		return json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
	}

}

// Test controller with hidden and public endpoints
class HiddenEndpointController implements Controller
{

	#[Get(path: '/api/debug')]
	#[Hidden(reason: 'Internal debug endpoint')]
	public function hiddenEndpoint(): array
	{
		return [];
	}

	#[Get(path: '/api/public')]
	public function publicEndpoint(): array
	{
		return [];
	}

}

// Test controller with multiple endpoints
class MultipleEndpointsController implements Controller
{

	#[Get(path: '/api/users')]
	public function list(): array
	{
		return [];
	}

	#[Get(path: '/api/users/{id}')]
	public function get(int $id): array
	{
		return [];
	}

	#[Get(path: '/api/debug')]
	#[Hidden]
	public function debug(): array
	{
		return [];
	}

}

// Test controller with all hidden endpoints
class AllHiddenController implements Controller
{

	#[Get(path: '/api/internal/health')]
	#[Hidden]
	public function health(): array
	{
		return [];
	}

	#[Get(path: '/api/internal/metrics')]
	#[Hidden]
	public function metrics(): array
	{
		return [];
	}

}

class HiddenPropertyController implements Controller
{

	#[Post(path: '/api/notes')]
	public function create(HiddenPropertyDto $input): array
	{
		return [];
	}

}

class HiddenPropertyDto
{

	public string $name;

	#[Hidden(reason: 'Set by server')]
	public string $internalNote = '';

}

class HiddenPropertyMultipartDto
{

	public string $title;

	#[FileUpload]
	public UploadedFile $file;

	#[Hidden]
	public string $internalNote;

}
