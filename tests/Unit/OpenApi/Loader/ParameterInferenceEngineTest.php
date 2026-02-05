<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Loader;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\RequestParameter;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\OpenApi\Loader\ParameterInferenceEngine;
use Sabservis\Api\Schema\EndpointParameter;

final class ParameterInferenceEngineTest extends TestCase
{

	private ParameterInferenceEngine $engine;

	protected function setUp(): void
	{
		$this->engine = new ParameterInferenceEngine();
	}

	#[Test]
	public function inferFromMethodSignatureDetectsPathParameter(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'getUser');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/users/{id}', $parameters);

		self::assertArrayHasKey('id', $parameters);
		self::assertSame('int', $parameters['id']['type']);
		self::assertSame(EndpointParameter::InPath, $parameters['id']['in']);
		self::assertTrue($parameters['id']['required']);
	}

	#[Test]
	public function inferFromMethodSignatureDetectsQueryParameter(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'listUsers');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/users', $parameters);

		self::assertArrayHasKey('limit', $parameters);
		self::assertSame('int', $parameters['limit']['type']);
		self::assertSame(EndpointParameter::InQuery, $parameters['limit']['in']);
		self::assertFalse($parameters['limit']['required']);
	}

	#[Test]
	public function inferFromMethodSignatureSkipsApiRequestParameter(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'withRequest');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/test', $parameters);

		self::assertArrayNotHasKey('request', $parameters);
	}

	#[Test]
	public function inferFromMethodSignatureSkipsApiResponseParameter(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'withResponse');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/test', $parameters);

		self::assertArrayNotHasKey('response', $parameters);
	}

	#[Test]
	public function inferFromMethodSignatureSkipsAlreadyDefinedParameters(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'getUser');
		$parameters = [
			'id' => [
				'name' => 'id',
				'type' => 'string',
				'in' => EndpointParameter::InPath,
				'required' => true,
				'description' => 'User ID',
			],
		];

		$this->engine->inferFromMethodSignature($method, '/users/{id}', $parameters);

		// Original definition should be preserved
		self::assertSame('string', $parameters['id']['type']);
		self::assertSame('User ID', $parameters['id']['description']);
	}

	#[Test]
	public function inferFromMethodSignatureHandlesNullableQueryParameter(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'searchUsers');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/users/search', $parameters);

		self::assertArrayHasKey('query', $parameters);
		self::assertSame('string', $parameters['query']['type']);
		self::assertFalse($parameters['query']['required']);
	}

	#[Test]
	public function inferFromMethodSignatureHandlesUnionTypes(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'withUnionType');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/test', $parameters);

		self::assertArrayHasKey('date', $parameters);
		self::assertSame(DateTimeImmutable::class, $parameters['date']['type']);
	}

	#[Test]
	public function inferFromMethodSignatureMarksPathParametersAsRequired(): void
	{
		$method = new ReflectionMethod(InferenceTestController::class, 'withNullablePathParam');
		$parameters = [];

		$this->engine->inferFromMethodSignature($method, '/users/{id}', $parameters);

		// Path parameters are ALWAYS required, regardless of nullable
		self::assertTrue($parameters['id']['required']);
	}

	#[Test]
	public function extractQueryParametersFromRefExtractsProperties(): void
	{
		$parameters = $this->engine->extractQueryParametersFromRef(QueryDto::class);

		self::assertArrayHasKey('search', $parameters);
		self::assertSame('string', $parameters['search']['type']);
		self::assertSame(EndpointParameter::InQuery, $parameters['search']['in']);
		self::assertSame('Search term', $parameters['search']['description']);
		self::assertTrue($parameters['search']['required']);
	}

	#[Test]
	public function extractQueryParametersFromRefInfersTypeFromProperty(): void
	{
		$parameters = $this->engine->extractQueryParametersFromRef(QueryDto::class);

		self::assertArrayHasKey('page', $parameters);
		self::assertSame('int', $parameters['page']['type']);
	}

	#[Test]
	public function extractQueryParametersFromRefReturnsEmptyForNonExistentClass(): void
	{
		/** @var class-string $nonExistent */
		$nonExistent = 'NonExistentClass';
		$parameters = $this->engine->extractQueryParametersFromRef($nonExistent);

		self::assertSame([], $parameters);
	}

	#[Test]
	public function extractQueryParametersFromRefSkipsPropertiesWithoutAttribute(): void
	{
		$parameters = $this->engine->extractQueryParametersFromRef(QueryDto::class);

		self::assertArrayNotHasKey('internalField', $parameters);
	}

	#[Test]
	public function buildParameterBuildsFromRequestParameter(): void
	{
		$requestParam = new RequestParameter(
			name: 'userId',
			in: EndpointParameter::InPath,
			description: 'User identifier',
			required: true,
			deprecated: false,
		);

		$result = $this->engine->buildParameter($requestParam);

		self::assertSame('userId', $result['name']);
		self::assertSame(EndpointParameter::InPath, $result['in']);
		self::assertSame('User identifier', $result['description']);
		self::assertTrue($result['required']);
		self::assertFalse($result['deprecated']);
	}

	#[Test]
	public function buildParameterExtractsTypeFromSchema(): void
	{
		$requestParam = new RequestParameter(
			name: 'count',
			in: EndpointParameter::InQuery,
			schema: new Schema(type: 'integer'),
		);

		$result = $this->engine->buildParameter($requestParam);

		self::assertSame('integer', $result['type']);
	}

	#[Test]
	public function getDefaultParameterReturnsPathDefaults(): void
	{
		$result = $this->engine->getDefaultParameter('id', EndpointParameter::InPath);

		self::assertSame('id', $result['name']);
		self::assertSame(EndpointParameter::TypeString, $result['type']);
		self::assertSame(EndpointParameter::InPath, $result['in']);
		self::assertTrue($result['required']);
	}

	#[Test]
	public function getDefaultParameterReturnsQueryDefaults(): void
	{
		$result = $this->engine->getDefaultParameter('limit', EndpointParameter::InQuery);

		self::assertSame('limit', $result['name']);
		self::assertSame(EndpointParameter::TypeString, $result['type']);
		self::assertSame(EndpointParameter::InQuery, $result['in']);
		self::assertFalse($result['required']);
	}

	#[Test]
	public function buildParameterExtractsExample(): void
	{
		$requestParam = new RequestParameter(
			name: 'query',
			in: EndpointParameter::InQuery,
			example: 'search term',
		);

		$result = $this->engine->buildParameter($requestParam);

		self::assertSame('search term', $result['example']);
	}

	#[Test]
	public function buildParameterExtractsStyle(): void
	{
		$requestParam = new RequestParameter(
			name: 'ids',
			in: EndpointParameter::InQuery,
			style: 'form',
		);

		$result = $this->engine->buildParameter($requestParam);

		self::assertSame('form', $result['style']);
	}

	#[Test]
	public function buildParameterExtractsExplode(): void
	{
		$requestParam = new RequestParameter(
			name: 'ids',
			in: EndpointParameter::InQuery,
			explode: true,
		);

		$result = $this->engine->buildParameter($requestParam);

		self::assertTrue($result['explode']);
	}

	#[Test]
	public function buildParameterOmitsNullOptionalFields(): void
	{
		$requestParam = new RequestParameter(
			name: 'query',
			in: EndpointParameter::InQuery,
		);

		$result = $this->engine->buildParameter($requestParam);

		self::assertNull($result['example']);
		self::assertNull($result['style']);
		self::assertNull($result['explode']);
	}

}

// Test fixtures

class InferenceTestController
{

	public function getUser(int $id): void
	{
		// Test fixture - method signature is what matters
	}

	public function listUsers(int $limit = 10): void
	{
		// Test fixture - method signature is what matters
	}

	public function withRequest(ApiRequest $request): void
	{
		// Test fixture - method signature is what matters
	}

	public function withResponse(ApiResponse $response): void
	{
		// Test fixture - method signature is what matters
	}

	public function searchUsers(string|null $query): void
	{
		// Test fixture - method signature is what matters
	}

	public function withUnionType(DateTimeImmutable|null $date): void
	{
		// Test fixture - method signature is what matters
	}

	public function withNullablePathParam(int|null $id): void
	{
		// Test fixture - method signature is what matters
	}

}

class QueryDto
{

	#[Property(property: 'search', type: 'string', description: 'Search term', required: true)]
	public string $search;

	#[Property]
	public int $page;

	public string $internalField;

}
