<?php declare(strict_types = 1);

namespace Tests\Unit\Attribute\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\PathParameter;
use Sabservis\Api\Attribute\OpenApi\QueryParameter;
use Sabservis\Api\Attribute\OpenApi\RequestParameter;

/**
 * Tests for allowEmptyValue property in parameter attributes.
 * Verifies consistent naming with OpenAPI specification.
 */
final class AllowEmptyValueTest extends TestCase
{

	#[Test]
	public function requestParameterDefaultAllowEmptyValueIsFalse(): void
	{
		$param = new RequestParameter(name: 'filter');

		self::assertFalse($param->allowEmptyValue);
	}

	#[Test]
	public function requestParameterAllowEmptyValueCanBeSet(): void
	{
		$param = new RequestParameter(name: 'filter', allowEmptyValue: true);

		self::assertTrue($param->allowEmptyValue);
	}

	#[Test]
	public function requestParameterToOpenApiSpecIncludesAllowEmptyValue(): void
	{
		$param = new RequestParameter(name: 'filter', allowEmptyValue: true);

		$spec = $param->toOpenApiSpec();

		self::assertArrayHasKey('allowEmptyValue', $spec);
		self::assertTrue($spec['allowEmptyValue']);
	}

	#[Test]
	public function requestParameterToOpenApiSpecOmitsAllowEmptyValueWhenFalse(): void
	{
		$param = new RequestParameter(name: 'filter', allowEmptyValue: false);

		$spec = $param->toOpenApiSpec();

		// When false, it should not be included in spec (OpenAPI default is false)
		self::assertArrayNotHasKey('allowEmptyValue', $spec);
	}

	#[Test]
	public function queryParameterAllowEmptyValueCanBeSet(): void
	{
		$param = new QueryParameter(name: 'search', allowEmptyValue: true);

		self::assertTrue($param->allowEmptyValue);
	}

	#[Test]
	public function queryParameterToOpenApiSpecIncludesAllowEmptyValue(): void
	{
		$param = new QueryParameter(name: 'search', allowEmptyValue: true);

		$spec = $param->toOpenApiSpec();

		self::assertArrayHasKey('allowEmptyValue', $spec);
		self::assertTrue($spec['allowEmptyValue']);
	}

	#[Test]
	public function pathParameterAlwaysHasAllowEmptyValueFalse(): void
	{
		$param = new PathParameter(name: 'id');

		self::assertFalse($param->allowEmptyValue);
	}

	#[Test]
	public function pathParameterToOpenApiSpecOmitsAllowEmptyValue(): void
	{
		$param = new PathParameter(name: 'id');

		$spec = $param->toOpenApiSpec();

		// Path parameters cannot have empty values
		self::assertArrayNotHasKey('allowEmptyValue', $spec);
	}

	#[Test]
	public function openApiSpecNamingMatchesOpenApiStandard(): void
	{
		// This test documents that we use 'allowEmptyValue' which matches
		// the OpenAPI specification exactly
		// https://swagger.io/specification/ - Parameter Object

		$param = new QueryParameter(name: 'q', allowEmptyValue: true);
		$spec = $param->toOpenApiSpec();

		// Verify we use the correct OpenAPI field name
		self::assertArrayHasKey('allowEmptyValue', $spec);

		// Verify we DON'T use non-standard names
		self::assertArrayNotHasKey('allowEmpty', $spec);
		self::assertArrayNotHasKey('allow_empty', $spec);
		self::assertArrayNotHasKey('emptyAllowed', $spec);
	}

}
