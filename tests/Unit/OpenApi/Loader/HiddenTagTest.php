<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function array_column;
use function json_decode;
use function json_encode;

final class HiddenTagTest extends TestCase
{

	#[Test]
	public function loaderRecordsHiddenFlagOfTag(): void
	{
		$result = $this->load();

		self::assertTrue($result['tags']['Internal']['hidden']);
		self::assertFalse($result['tags']['Public']['hidden'] ?? false);
	}

	#[Test]
	public function hiddenTagIsNeitherListedNorAttachedToOperations(): void
	{
		$schema = (new ArrayHydrator())->hydrate($this->load());
		$spec = json_decode(json_encode((new OpenApiGenerator(new OpenApiConfig()))->generate($schema)), true);

		self::assertSame(['Public'], array_column($spec['tags'], 'name'));
		self::assertSame(['Public'], $spec['paths']['/public']['get']['tags']);
		// The operation itself stays documented - only the tag is hidden
		self::assertArrayHasKey('/internal', $spec['paths']);
		self::assertArrayNotHasKey('tags', $spec['paths']['/internal']['get']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load(): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('a')->setType(HiddenTagController::class);
		$containerBuilder->addDefinition('b')->setType(PublicTagController::class);

		return (new OpenApiAttributeLoader($containerBuilder))->load();
	}

}

#[Tag(name: 'Internal', description: 'Not for the public docs', hidden: true)]
final class HiddenTagController implements Controller
{

	#[Get(path: '/internal')]
	public function internal(): void
	{
		// fixture only
	}

}

#[Tag(name: 'Public')]
final class PublicTagController implements Controller
{

	#[Get(path: '/public')]
	public function public(): void
	{
		// fixture only
	}

}
