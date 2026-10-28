<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Tests\Unit\OpenApi\Fixtures\Collision\CollisionController;
use Tests\Unit\OpenApi\Fixtures\Collision\InlineOnlyController;
use Tests\Unit\OpenApi\Fixtures\Collision\PinnedMultipartController;
use Tests\Unit\OpenApi\Fixtures\Collision\PinnedNameController;
use Tests\Unit\OpenApi\Fixtures\Collision\SameContextController;
use function array_column;
use function array_keys;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class SchemaNameCollisionTest extends TestCase
{

	#[Test]
	public function collidingClassesGetNamespacePrefixedNames(): void
	{
		$schemas = $this->generate(CollisionController::class)['components']['schemas'];

		self::assertArrayNotHasKey('ItemDto', $schemas);
		self::assertSame(['sku', 'quantity'], array_keys($schemas['OrderItemDto']['properties']));
		self::assertSame(['description', 'price'], array_keys($schemas['InvoiceItemDto']['properties']));
	}

	#[Test]
	public function responsesReferenceTheirOwnClass(): void
	{
		$paths = $this->generate(CollisionController::class)['paths'];

		self::assertSame('#/components/schemas/OrderItemDto', $this->responseRef($paths, '/orders/item'));
		self::assertSame('#/components/schemas/InvoiceItemDto', $this->responseRef($paths, '/invoices/item'));
	}

	#[Test]
	public function nestedReferencesUseResolvedNames(): void
	{
		$cart = $this->generate(CollisionController::class)['components']['schemas']['CartDto'];

		self::assertSame('#/components/schemas/OrderItemDto', $cart['properties']['orderItem']['$ref']);
		self::assertSame('#/components/schemas/InvoiceItemDto', $cart['properties']['invoiceItems']['items']['$ref']);
	}

	#[Test]
	public function explicitSchemaNameAvoidsCollisionAndKeepsOtherNameStable(): void
	{
		$spec = $this->generate(PinnedNameController::class);
		$schemas = $spec['components']['schemas'];

		self::assertArrayHasKey('ItemDto', $schemas);
		self::assertArrayHasKey('ShippingLine', $schemas);
		self::assertSame(['sku', 'quantity'], array_keys($schemas['ItemDto']['properties']));
		self::assertSame('#/components/schemas/ShippingLine', $this->responseRef($spec['paths'], '/shipping/item'));
		self::assertSame('#/components/schemas/ItemDto', $this->responseRef($spec['paths'], '/orders/item'));
	}

	#[Test]
	public function collidingClassesInOneResponseKeepTheirOwnRefs(): void
	{
		$paths = $this->generate(SameContextController::class)['paths'];
		$schema = $paths['/items']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame(
			['#/components/schemas/OrderItemDto', '#/components/schemas/InvoiceItemDto'],
			array_column($schema['properties']['data']['items']['oneOf'], '$ref'),
		);
	}

	#[Test]
	public function collidingClassesInInlineResponseSchemaKeepTheirOwnRefs(): void
	{
		$paths = $this->generate(SameContextController::class)['paths'];
		$schema = $paths['/items/any']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame(
			['#/components/schemas/OrderItemDto', '#/components/schemas/InvoiceItemDto'],
			array_column($schema['oneOf'], '$ref'),
		);
	}

	#[Test]
	public function collidingClassesInInlineRequestBodyKeepTheirOwnRefs(): void
	{
		$spec = $this->generate(SameContextController::class);
		$schema = $spec['paths']['/items/import']['post']['requestBody']['content']['application/json']['schema'];

		self::assertSame(
			['#/components/schemas/InvoiceItemDto', '#/components/schemas/ShippingLine'],
			array_column($schema['oneOf'], '$ref'),
		);
		self::assertStringNotContainsString('_class', json_encode($spec, JSON_THROW_ON_ERROR));
	}

	#[Test]
	public function requestBodyReferencesItsOwnClass(): void
	{
		$paths = $this->generate(SameContextController::class)['paths'];

		self::assertSame(
			'#/components/schemas/ShippingLine',
			$paths['/shipping/items']['post']['requestBody']['content']['application/json']['schema']['$ref'],
		);
	}

	#[Test]
	public function multipartPropertyReferencesPinnedNameOfItsOwnClass(): void
	{
		$spec = $this->generate(PinnedMultipartController::class);
		$schema = $spec['paths']['/basket']['post']['requestBody']['content']['multipart/form-data']['schema'];

		self::assertSame(['total'], array_keys($spec['components']['schemas']['CartDto']['properties']));
		self::assertSame('#/components/schemas/CartDto', $schema['properties']['basket']['$ref']);
		self::assertSame('#/components/schemas/CollisionCartDto', $this->responseRef($spec['paths'], '/cart'));
	}

	#[Test]
	public function classReferencedOnlyFromInlineRequestBodyIsRegistered(): void
	{
		$spec = $this->generate(InlineOnlyController::class);
		$schema = $spec['paths']['/inline']['post']['requestBody']['content']['application/json']['schema'];

		self::assertSame('#/components/schemas/ItemDto', $schema['oneOf'][0]['$ref']);
		self::assertSame(['sku', 'quantity'], array_keys($spec['components']['schemas']['ItemDto']['properties']));
	}

	/**
	 * @param array<string, mixed> $paths
	 */
	private function responseRef(array $paths, string $path): string
	{
		return $paths[$path]['get']['responses']['200']['content']['application/json']['schema']['$ref'];
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<string, mixed>
	 */
	private function generate(string $controllerClass): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('controller')->setType($controllerClass);
		$schema = (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());

		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'Test API', version: '1.0.0')))->generate($schema);

		return json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
	}

}
