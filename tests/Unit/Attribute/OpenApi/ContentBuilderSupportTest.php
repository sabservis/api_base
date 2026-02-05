<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\ContentBuilderSupport;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\MediaType;

final class ContentBuilderSupportTest extends TestCase
{

	#[Test]
	public function buildContentSpecReturnsEmptyArrayForNull(): void
	{
		$helper = $this->createContentHelper();

		$result = $helper->testBuildContentSpec(null);

		self::assertSame([], $result);
	}

	#[Test]
	public function buildContentSpecBuildsJsonContent(): void
	{
		$helper = $this->createContentHelper();
		$jsonContent = new JsonContent(type: 'object');

		$result = $helper->testBuildContentSpec([$jsonContent]);

		self::assertArrayHasKey('application/json', $result);
		self::assertArrayHasKey('schema', $result['application/json']);
		self::assertSame('object', $result['application/json']['schema']['type']);
	}

	#[Test]
	public function buildContentSpecBuildsMediaType(): void
	{
		$helper = $this->createContentHelper();
		$mediaType = new MediaType(
			mediaType: 'application/xml',
			schema: new \Sabservis\Api\Attribute\OpenApi\Schema(type: 'string'),
		);

		$result = $helper->testBuildContentSpec([$mediaType]);

		self::assertArrayHasKey('application/xml', $result);
	}

	#[Test]
	public function buildContentSpecHandlesMultipleContentTypes(): void
	{
		$helper = $this->createContentHelper();
		$jsonContent = new JsonContent(type: 'object');
		$mediaType = new MediaType(
			mediaType: 'application/xml',
			schema: new \Sabservis\Api\Attribute\OpenApi\Schema(type: 'string'),
		);

		$result = $helper->testBuildContentSpec([$jsonContent, $mediaType]);

		self::assertArrayHasKey('application/json', $result);
		self::assertArrayHasKey('application/xml', $result);
	}

	#[Test]
	public function buildJsonContentFromRefBuildsCorrectStructure(): void
	{
		$helper = $this->createContentHelper();

		$result = $helper->testBuildJsonContentFromRef('UserDto');

		self::assertSame([
			'application/json' => [
				'schema' => ['$ref' => '#/components/schemas/UserDto'],
			],
		], $result);
	}

	/**
	 * @return object
	 */
	private function createContentHelper(): object
	{
		return new class {

			use ContentBuilderSupport;

			/**
			 * @param array<\Sabservis\Api\Attribute\OpenApi\JsonContent|\Sabservis\Api\Attribute\OpenApi\MediaType>|null $contentArray
			 * @return array<string, mixed>
			 */
			public function testBuildContentSpec(array|null $contentArray): array
			{
				return $this->buildContentSpec($contentArray);
			}

			/**
			 * @return array<string, array<string, mixed>>
			 */
			public function testBuildJsonContentFromRef(string $refName): array
			{
				return $this->buildJsonContentFromRef($refName);
			}

		};
	}

}
