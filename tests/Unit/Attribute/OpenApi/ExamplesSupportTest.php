<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Examples;
use Sabservis\Api\Attribute\OpenApi\ExamplesSupport;

final class ExamplesSupportTest extends TestCase
{

	#[Test]
	public function buildExamplesMapReturnsEmptyForNull(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testBuildExamplesMap(null);

		self::assertSame([], $result);
	}

	#[Test]
	public function buildExamplesMapReturnsEmptyForEmptyArray(): void
	{
		$helper = $this->createHelper();

		$result = $helper->testBuildExamplesMap([]);

		self::assertSame([], $result);
	}

	#[Test]
	public function buildExamplesMapBuildsNamedExamples(): void
	{
		$helper = $this->createHelper();
		$examples = [
			new Examples(example: 'success', summary: 'Success response', value: ['id' => 1]),
			new Examples(example: 'error', summary: 'Error response', value: ['error' => 'Not found']),
		];

		$result = $helper->testBuildExamplesMap($examples);

		self::assertArrayHasKey('success', $result);
		self::assertArrayHasKey('error', $result);
		self::assertSame('Success response', $result['success']['summary']);
		self::assertSame('Error response', $result['error']['summary']);
	}

	#[Test]
	public function buildExamplesMapUsesDefaultForUnnamedExamples(): void
	{
		$helper = $this->createHelper();
		$examples = [
			new Examples(summary: 'Default example', value: ['data' => 'test']),
		];

		$result = $helper->testBuildExamplesMap($examples);

		self::assertArrayHasKey('default', $result);
		self::assertSame('Default example', $result['default']['summary']);
	}

	#[Test]
	public function buildExamplesMapPreservesExampleValues(): void
	{
		$helper = $this->createHelper();
		$examples = [
			new Examples(example: 'test', value: ['nested' => ['data' => 123]]),
		];

		$result = $helper->testBuildExamplesMap($examples);

		self::assertSame(['nested' => ['data' => 123]], $result['test']['value']);
	}

	/**
	 * @return object
	 */
	private function createHelper(): object
	{
		return new class {

			use ExamplesSupport;

			/**
			 * @param array<Examples>|null $examples
			 * @return array<string, mixed>
			 */
			public function testBuildExamplesMap(array|null $examples): array
			{
				return $this->buildExamplesMap($examples);
			}

		};
	}

}
