<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Encoding;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;

final class EncodingTest extends TestCase
{

	#[Test]
	public function fileUploadWithEncodingContentType(): void
	{
		$spec = $this->generateSpecForController(ControllerWithFileEncoding::class);

		$requestBody = $spec['paths']['/upload']['post']['requestBody'];
		$multipart = $requestBody['content']['multipart/form-data'];

		self::assertArrayHasKey('encoding', $multipart);
		self::assertArrayHasKey('document', $multipart['encoding']);
		self::assertSame('application/pdf', $multipart['encoding']['document']['contentType']);
	}

	#[Test]
	public function fileUploadWithMultipleEncodings(): void
	{
		$spec = $this->generateSpecForController(ControllerWithMultipleEncodings::class);

		$requestBody = $spec['paths']['/upload']['post']['requestBody'];
		$encoding = $requestBody['content']['multipart/form-data']['encoding'];

		self::assertArrayHasKey('document', $encoding);
		self::assertArrayHasKey('metadata', $encoding);
		self::assertSame('application/pdf', $encoding['document']['contentType']);
		self::assertSame('application/json', $encoding['metadata']['contentType']);
	}

	#[Test]
	public function encodingWithStyleAndExplode(): void
	{
		$spec = $this->generateSpecForController(ControllerWithEncodingStyle::class);

		$requestBody = $spec['paths']['/upload']['post']['requestBody'];
		$encoding = $requestBody['content']['multipart/form-data']['encoding'];

		self::assertArrayHasKey('tags', $encoding);
		self::assertSame('form', $encoding['tags']['style']);
		self::assertTrue($encoding['tags']['explode']);
	}

	#[Test]
	public function fileUploadWithoutEncodingHasNoEncodingKey(): void
	{
		$spec = $this->generateSpecForController(ControllerWithoutEncoding::class);

		$requestBody = $spec['paths']['/upload']['post']['requestBody'];
		$multipart = $requestBody['content']['multipart/form-data'];

		self::assertArrayNotHasKey('encoding', $multipart);
	}

	/**
	 * @param class-string $controllerClass
	 * @return array<string, mixed>
	 */
	private function generateSpecForController(string $controllerClass): array
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('testController')
			->setType($controllerClass);

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
		$json = json_encode($spec);
		assert($json !== false);

		return json_decode($json, true);
	}

}

// Test controllers

class ControllerWithFileEncoding implements Controller
{

	/** @return array<mixed> */
	#[Post(path: '/upload')]
	#[FileUpload(name: 'document')]
	#[Encoding(property: 'document', contentType: 'application/pdf')]
	public function upload(): array
	{
		return [];
	}

}

class ControllerWithMultipleEncodings implements Controller
{

	/** @return array<mixed> */
	#[Post(path: '/upload')]
	#[FileUpload(name: 'document')]
	#[FileUpload(name: 'metadata')]
	#[Encoding(property: 'document', contentType: 'application/pdf')]
	#[Encoding(property: 'metadata', contentType: 'application/json')]
	public function upload(): array
	{
		return [];
	}

}

class ControllerWithEncodingStyle implements Controller
{

	/** @return array<mixed> */
	#[Post(path: '/upload')]
	#[FileUpload(name: 'file')]
	#[FileUpload(name: 'tags')]
	#[Encoding(property: 'tags', style: 'form', explode: true)]
	public function upload(): array
	{
		return [];
	}

}

class ControllerWithoutEncoding implements Controller
{

	/** @return array<mixed> */
	#[Post(path: '/upload')]
	#[FileUpload(name: 'file')]
	public function upload(): array
	{
		return [];
	}

}
