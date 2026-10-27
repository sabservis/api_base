<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Generator\SchemaBuilder;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class TypeMappingSchemaTest extends TestCase
{

	#[Test]
	public function untypedArrayPropertyHasItems(): void
	{
		$tags = $this->properties(TypeMappingDto::class)['tags'];

		self::assertSame('array', $tags['type']);
		self::assertArrayHasKey('items', $tags);
	}

	#[Test]
	public function typedArrayPropertyKeepsItems(): void
	{
		$names = $this->properties(TypeMappingDto::class)['names'];

		self::assertSame(['type' => 'string'], $names['items']);
	}

	#[Test]
	public function mixedPropertyHasNoType(): void
	{
		$payload = $this->properties(TypeMappingDto::class)['payload'];

		self::assertArrayNotHasKey('type', $payload);
	}

	#[Test]
	public function selfPropertyReferencesDeclaringClass(): void
	{
		$parent = $this->properties(TreeNodeDto::class)['parent'];

		self::assertSame('#/components/schemas/TreeNodeDto', $parent['oneOf'][0]['$ref'] ?? $parent['$ref'] ?? null);
	}

	#[Test]
	public function arrayQueryParameterHasItems(): void
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')->setType(ArrayQueryController::class);
		$schema = (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());
		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'Test API', version: '1.0.0')))->generate($schema);
		$data = json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

		$parameter = $data['paths']['/items']['get']['parameters'][0];

		self::assertSame('array', $parameter['schema']['type']);
		self::assertArrayHasKey('items', $parameter['schema']);
	}

	/**
	 * @param class-string $class
	 * @return array<string, array<string, mixed>>
	 */
	private function properties(string $class): array
	{
		$builder = new SchemaBuilder();
		$name = $builder->registerClass($class);
		$json = json_encode($builder->getSchemas()[$name], JSON_THROW_ON_ERROR);

		return json_decode($json, true, 512, JSON_THROW_ON_ERROR)['properties'];
	}

}

final class TypeMappingDto
{

	/** @var array<mixed> */
	public array $tags;

	/** @var array<string> */
	#[Property(type: 'array', items: new Items(type: 'string'))]
	public array $names;

	public mixed $payload;

}

final class TreeNodeDto
{

	public string $name;

	public self|null $parent = null;

}

final class ArrayQueryController implements Controller
{

	/**
	 * @param array<string> $ids
	 * @return array<mixed>
	 */
	#[Get(path: '/items')]
	public function list(array $ids = []): array
	{
		return $ids;
	}

}
