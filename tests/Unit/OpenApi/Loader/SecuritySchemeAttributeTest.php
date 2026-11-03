<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Security;
use Sabservis\Api\Attribute\OpenApi\SecurityScheme;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;

final class SecuritySchemeAttributeTest extends TestCase
{

	#[Test]
	public function loaderCollectsSecuritySchemeAttributes(): void
	{
		$result = $this->load();

		self::assertSame(
			['type' => 'apiKey', 'name' => 'X-API-Key', 'in' => 'header'],
			$result['securitySchemes']['ApiKeyAuth'],
		);
	}

	#[Test]
	public function attributeSchemesAreMergedWithConfiguredOnesInComponents(): void
	{
		$schema = (new ArrayHydrator())->hydrate($this->load());
		$config = new OpenApiConfig(
			title: 'Test API',
			version: '1.0.0',
			securitySchemes: ['Bearer' => ['type' => 'http', 'scheme' => 'bearer']],
		);

		$spec = json_decode(json_encode((new OpenApiGenerator($config))->generate($schema)), true);

		self::assertSame('bearer', $spec['components']['securitySchemes']['Bearer']['scheme']);
		self::assertSame('X-API-Key', $spec['components']['securitySchemes']['ApiKeyAuth']['name']);
		// #[Security] on the method references the attribute-declared scheme
		self::assertSame([['ApiKeyAuth' => []]], $spec['paths']['/secured']['get']['security']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load(): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('c')->setType(SecuritySchemeController::class);

		return (new OpenApiAttributeLoader($containerBuilder))->load();
	}

}

#[SecurityScheme(securityScheme: 'ApiKeyAuth', type: 'apiKey', name: 'X-API-Key', in: 'header')]
final class SecuritySchemeController implements Controller
{

	#[Get(path: '/secured')]
	#[Security([['ApiKeyAuth' => []]])]
	public function secured(): void
	{
		// fixture only
	}

}
