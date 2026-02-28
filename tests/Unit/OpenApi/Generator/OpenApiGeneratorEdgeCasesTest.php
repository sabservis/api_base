<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Generator;

use DateTimeImmutable;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\AdditionalProperties;
use Sabservis\Api\Attribute\OpenApi\Examples;
use Sabservis\Api\Attribute\OpenApi\FileResponse as FileResponseAttribute;
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\EndpointResponse;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function array_column;
use function file_put_contents;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Edge case tests for OpenApiGenerator covering:
 * - File downloads (FileResponse)
 * - File uploads (multipart/form-data)
 * - oneOf/anyOf/allOf in responses
 * - List wrappers (with/without meta)
 * - Inline content specs
 * - Combined scenarios
 */
final class OpenApiGeneratorEdgeCasesTest extends TestCase
{

	// ==================== FILE DOWNLOAD TESTS ====================

	#[Test]
	public function fileDownloadResponseGeneratesBinarySchema(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\ReportController', 'downloadPdf');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/reports/{id}/pdf');

		$response = new EndpointResponse('200', 'PDF report download');
		$response->setFileContentType('application/pdf');
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseContent = $specArray['paths']['/reports/{id}/pdf']['get']['responses']['200']['content'];

		self::assertArrayHasKey('application/pdf', $responseContent);
		self::assertSame('string', $responseContent['application/pdf']['schema']['type']);
		self::assertSame('binary', $responseContent['application/pdf']['schema']['format']);
	}

	#[Test]
	public function fileDownloadWithDifferentContentTypes(): void
	{
		$spec = $this->generateSpecForController(FileDownloadController::class);

		// PDF download
		$pdfResponse = $spec['paths']['/download/pdf']['get']['responses']['200']['content'];
		self::assertArrayHasKey('application/pdf', $pdfResponse);

		// Excel download
		$excelResponse = $spec['paths']['/download/excel']['get']['responses']['200']['content'];
		self::assertArrayHasKey('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $excelResponse);

		// Image download
		$imageResponse = $spec['paths']['/download/image']['get']['responses']['200']['content'];
		self::assertArrayHasKey('image/png', $imageResponse);
	}

	// ==================== FILE UPLOAD TESTS ====================

	#[Test]
	public function fileUploadGeneratesMultipartFormData(): void
	{
		$spec = $this->generateSpecForController(FileUploadController::class);

		$requestBody = $spec['paths']['/upload/single']['post']['requestBody'];

		self::assertArrayHasKey('content', $requestBody);
		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);

		$schema = $requestBody['content']['multipart/form-data']['schema'];
		self::assertSame('object', $schema['type']);
		self::assertArrayHasKey('file', $schema['properties']);
		self::assertSame('string', $schema['properties']['file']['type']);
		self::assertSame('binary', $schema['properties']['file']['format']);
	}

	#[Test]
	public function multipleFileUploadGeneratesArraySchema(): void
	{
		$spec = $this->generateSpecForController(FileUploadController::class);

		$schema = $spec['paths']['/upload/multiple']['post']['requestBody']['content']['multipart/form-data']['schema'];

		self::assertSame('array', $schema['properties']['files']['type']);
		self::assertSame('string', $schema['properties']['files']['items']['type']);
		self::assertSame('binary', $schema['properties']['files']['items']['format']);
	}

	#[Test]
	public function mixedFileUploadWithRequiredField(): void
	{
		$spec = $this->generateSpecForController(FileUploadController::class);

		$schema = $spec['paths']['/upload/mixed']['post']['requestBody']['content']['multipart/form-data']['schema'];

		// Has both properties
		self::assertArrayHasKey('avatar', $schema['properties']);
		self::assertArrayHasKey('documents', $schema['properties']);

		// Only avatar is required
		self::assertContains('avatar', $schema['required']);
		self::assertNotContains('documents', $schema['required'] ?? []);
	}

	// ==================== ONEOF/ANYOF/ALLOF TESTS ====================

	#[Test]
	public function responseWithOneOfGeneratesCorrectSchema(): void
	{
		$spec = $this->generateSpecForController(PolymorphicResponseController::class);

		// Debug: dump the spec to see exact structure
		// file_put_contents('/tmp/openapi_debug.json', json_encode($spec, JSON_PRETTY_PRINT));

		$responseSchema = $spec['paths']['/items']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('array', $responseSchema['type']);
		self::assertArrayHasKey('oneOf', $responseSchema['items']);
		self::assertCount(2, $responseSchema['items']['oneOf']);

		// Verify refs
		$refs = array_column($responseSchema['items']['oneOf'], '$ref');
		self::assertContains('#/components/schemas/ProductDto', $refs);
		self::assertContains('#/components/schemas/ServiceDto', $refs);
	}

	#[Test]
	public function dumpFullSpecForValidation(): void
	{
		$spec = $this->generateSpecForController(PolymorphicResponseController::class);

		// Save to temp file for manual OpenAPI validation
		$json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		file_put_contents('/tmp/openapi_test_spec.json', $json);

		// Basic structural validation according to OpenAPI 3.0.3 spec
		self::assertSame('3.0.3', $spec['openapi']);
		self::assertArrayHasKey('info', $spec);
		self::assertArrayHasKey('title', $spec['info']);
		self::assertArrayHasKey('version', $spec['info']);
		self::assertArrayHasKey('paths', $spec);

		// Validate oneOf structure - must be array of schema objects
		$oneOf = $spec['paths']['/items']['get']['responses']['200']['content']['application/json']['schema']['items']['oneOf'];

		foreach ($oneOf as $schema) {
			// Each item in oneOf must be a Schema Object (with $ref or type)
			self::assertTrue(
				isset($schema['$ref']) || isset($schema['type']),
				'Each oneOf item must be a valid Schema Object with $ref or type',
			);
		}
	}

	#[Test]
	public function responseWithAnyOfGeneratesCorrectSchema(): void
	{
		$spec = $this->generateSpecForController(PolymorphicResponseController::class);

		$responseSchema = $spec['paths']['/flexible']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertArrayHasKey('anyOf', $responseSchema['items']);
		self::assertCount(2, $responseSchema['items']['anyOf']);
	}

	#[Test]
	public function nestedOneOfInResponseWorks(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'nested');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/nested');

		$response = new EndpointResponse('200', 'Nested response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'data' => [
							'type' => 'array',
							'items' => [
								'oneOf' => [
									['$ref' => '#/components/schemas/TypeA'],
									['$ref' => '#/components/schemas/TypeB'],
								],
							],
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/nested']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertArrayHasKey('oneOf', $responseSchema['properties']['data']['items']);
	}

	// ==================== LIST WRAPPER TESTS ====================

	#[Test]
	public function listWithMetaGeneratesCorrectStructure(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\UserController', 'list');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/users');

		$response = new EndpointResponse('200', 'User list with pagination');
		$response->setEntity(UserDto::class);
		$response->setWrapperType(EndpointResponse::WrapperListWithMeta);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/users']['get']['responses']['200']['content']['application/json']['schema'];

		// Should have data and meta properties
		self::assertSame('object', $responseSchema['type']);
		self::assertArrayHasKey('data', $responseSchema['properties']);
		self::assertArrayHasKey('meta', $responseSchema['properties']);

		// data should be array of UserDto refs
		self::assertSame('array', $responseSchema['properties']['data']['type']);
		self::assertSame('#/components/schemas/UserDto', $responseSchema['properties']['data']['items']['$ref']);

		// meta should have pagination fields
		$metaProps = $responseSchema['properties']['meta']['properties'];
		self::assertArrayHasKey('totalCount', $metaProps);
		self::assertArrayHasKey('limit', $metaProps);
		self::assertArrayHasKey('offset', $metaProps);
	}

	#[Test]
	public function listNoMetaGeneratesSimpleArray(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TagController', 'list');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/tags');

		$response = new EndpointResponse('200', 'Tag list');
		$response->setEntity(TagDto::class);
		$response->setWrapperType(EndpointResponse::WrapperListNoMeta);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/tags']['get']['responses']['200']['content']['application/json']['schema'];

		// Should be simple array, not object wrapper
		self::assertSame('array', $responseSchema['type']);
		self::assertSame('#/components/schemas/TagDto', $responseSchema['items']['$ref']);
		self::assertArrayNotHasKey('properties', $responseSchema);
	}

	// ==================== REQUEST BODY CONTENT SPEC TESTS ====================

	#[Test]
	public function requestBodyWithContentSpecGeneratesCorrectSchema(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'create');
		$endpoint->addMethod('POST');
		$endpoint->setMask('/items');

		$requestBody = new EndpointRequestBody();
		$requestBody->setRequired(true);
		$requestBody->setContentSpec([
			'application/json' => [
				'schema' => [
					'oneOf' => [
						['$ref' => '#/components/schemas/ProductCreateDto'],
						['$ref' => '#/components/schemas/ServiceCreateDto'],
					],
				],
			],
		]);
		$endpoint->setRequestBody($requestBody);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$requestBodySchema = $specArray['paths']['/items']['post']['requestBody']['content']['application/json']['schema'];

		self::assertArrayHasKey('oneOf', $requestBodySchema);
		self::assertCount(2, $requestBodySchema['oneOf']);
	}

	// ==================== COMBINED SCENARIOS ====================

	#[Test]
	public function fileUploadWithPolymorphicResponse(): void
	{
		$spec = $this->generateSpecForController(CombinedController::class);

		// Request should be multipart upload
		$requestBody = $spec['paths']['/process']['post']['requestBody'];
		self::assertArrayHasKey('multipart/form-data', $requestBody['content']);

		// Response should have oneOf
		$responseSchema = $spec['paths']['/process']['post']['responses']['200']['content']['application/json']['schema'];
		self::assertArrayHasKey('oneOf', $responseSchema);
	}

	#[Test]
	public function multipleResponseCodesWithDifferentSchemas(): void
	{
		$spec = $this->generateSpecForController(MultiResponseController::class);

		$responses = $spec['paths']['/action']['post']['responses'];

		// 200 - Success with DTO
		self::assertArrayHasKey('200', $responses);
		self::assertArrayHasKey('application/json', $responses['200']['content']);

		// 202 - Accepted with different structure
		self::assertArrayHasKey('202', $responses);

		// 400 - Error
		self::assertArrayHasKey('400', $responses);
	}

	// ==================== SCHEMA REFERENCE TRACKING ====================

	#[Test]
	public function oneOfReferencesAreRegisteredInComponents(): void
	{
		$spec = $this->generateSpecForController(PolymorphicResponseController::class);

		// Verify the refs are correctly formatted in the response
		$responseSchema = $spec['paths']['/items']['get']['responses']['200']['content']['application/json']['schema'];
		$oneOfRefs = $responseSchema['items']['oneOf'];

		// Refs should be correctly formatted
		self::assertSame('#/components/schemas/ProductDto', $oneOfRefs[0]['$ref']);
		self::assertSame('#/components/schemas/ServiceDto', $oneOfRefs[1]['$ref']);

		// Schemas should be auto-registered in components
		self::assertArrayHasKey('components', $spec);
		self::assertArrayHasKey('schemas', $spec['components']);
		self::assertArrayHasKey('ProductDto', $spec['components']['schemas']);
		self::assertArrayHasKey('ServiceDto', $spec['components']['schemas']);

		// Verify schema structure
		self::assertSame('object', $spec['components']['schemas']['ProductDto']['type']);
		self::assertArrayHasKey('id', $spec['components']['schemas']['ProductDto']['properties']);
		self::assertArrayHasKey('name', $spec['components']['schemas']['ProductDto']['properties']);
		self::assertArrayHasKey('price', $spec['components']['schemas']['ProductDto']['properties']);
	}

	#[Test]
	public function noContentResponseHasNoContentField(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\ResourceController', 'delete');
		$endpoint->addMethod('DELETE');
		$endpoint->setMask('/resources/{id}');

		$response = new EndpointResponse('204', 'Resource deleted');
		// No entity, no contentSpec - pure 204 No Content
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$response204 = $specArray['paths']['/resources/{id}']['delete']['responses']['204'];

		self::assertSame('Resource deleted', $response204['description']);
		// Content should be null/absent for 204
		self::assertArrayNotHasKey('content', $response204);
	}

	#[Test]
	public function entityTakesPrecedenceOverContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'get');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/test');

		$response = new EndpointResponse('200', 'Test response');
		$response->setEntity(UserDto::class);
		// contentSpec should be ignored when entity is set
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'string',
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/test']['get']['responses']['200']['content']['application/json']['schema'];

		// Should use entity reference, not the inline string type
		self::assertArrayHasKey('$ref', $responseSchema);
		self::assertSame('#/components/schemas/UserDto', $responseSchema['$ref']);
	}

	#[Test]
	public function allOfForSchemaComposition(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'get');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/composed');

		$response = new EndpointResponse('200', 'Composed schema');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'allOf' => [
						['$ref' => '#/components/schemas/BaseDto'],
						[
							'type' => 'object',
							'properties' => [
								'extraField' => ['type' => 'string'],
							],
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/composed']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertArrayHasKey('allOf', $responseSchema);
		self::assertCount(2, $responseSchema['allOf']);

		// First item should be a reference
		self::assertArrayHasKey('$ref', $responseSchema['allOf'][0]);

		// Second item should be inline object with properties
		self::assertSame('object', $responseSchema['allOf'][1]['type']);
		self::assertArrayHasKey('extraField', $responseSchema['allOf'][1]['properties']);
	}

	#[Test]
	public function deeplyNestedOneOfStructure(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'complex');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/complex');

		// Response: { data: [ { items: [ oneOf: [A, B] ] } ] }
		$response = new EndpointResponse('200', 'Complex nested structure');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'data' => [
							'type' => 'array',
							'items' => [
								'type' => 'object',
								'properties' => [
									'items' => [
										'type' => 'array',
										'items' => [
											'oneOf' => [
												['$ref' => '#/components/schemas/TypeA'],
												['$ref' => '#/components/schemas/TypeB'],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/complex']['get']['responses']['200']['content']['application/json']['schema'];

		// Navigate to deeply nested oneOf
		$nestedItems = $responseSchema['properties']['data']['items']['properties']['items']['items'];
		self::assertArrayHasKey('oneOf', $nestedItems);
		self::assertCount(2, $nestedItems['oneOf']);
	}

	#[Test]
	public function multipleMediaTypesInResponse(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\ExportController', 'export');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/export');

		$response = new EndpointResponse('200', 'Export data');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'data' => ['type' => 'array', 'items' => ['type' => 'object']],
					],
				],
			],
			'text/csv' => [
				'schema' => [
					'type' => 'string',
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseContent = $specArray['paths']['/export']['get']['responses']['200']['content'];

		self::assertArrayHasKey('application/json', $responseContent);
		self::assertArrayHasKey('text/csv', $responseContent);
		self::assertSame('string', $responseContent['text/csv']['schema']['type']);
	}

	// ==================== DATETIME PARAMETER TESTS ====================

	#[Test]
	public function dateTimeParameterGeneratesStringWithDateTimeFormat(): void
	{
		$spec = $this->generateSpecForController(DateTimeParameterController::class);

		$parameters = $spec['paths']['/applications/download']['get']['parameters'];

		// Find the 'since' parameter
		$sinceParam = null;

		foreach ($parameters as $param) {
			if ($param['name'] === 'since') {
				$sinceParam = $param;

				break;
			}
		}

		self::assertNotNull($sinceParam, 'Parameter "since" should exist');

		// Should be query parameter
		self::assertSame('query', $sinceParam['in']);

		// Should NOT be required (nullable type)
		self::assertNull($sinceParam['required'] ?? null);

		// Schema should be string with date-time format, NOT an object/$ref
		self::assertSame('string', $sinceParam['schema']['type']);
		self::assertSame('date-time', $sinceParam['schema']['format']);
	}

	#[Test]
	public function requiredDateTimeParameterIsMarkedRequired(): void
	{
		$spec = $this->generateSpecForController(DateTimeParameterController::class);

		$parameters = $spec['paths']['/events']['get']['parameters'];

		$fromParam = null;

		foreach ($parameters as $param) {
			if ($param['name'] === 'from') {
				$fromParam = $param;

				break;
			}
		}

		self::assertNotNull($fromParam);
		self::assertTrue($fromParam['required']);
		self::assertSame('string', $fromParam['schema']['type']);
		self::assertSame('date-time', $fromParam['schema']['format']);
	}

	// ==================== OPERATION ATTRIBUTE TESTS ====================

	#[Test]
	public function operationSummaryIsGeneratedCorrectly(): void
	{
		$spec = $this->generateSpecForController(OperationAttributeController::class);

		$operation = $spec['paths']['/users']['get'];

		self::assertSame('List all users', $operation['summary']);
	}

	#[Test]
	public function operationDeprecatedIsGeneratedCorrectly(): void
	{
		$spec = $this->generateSpecForController(OperationAttributeController::class);

		$operation = $spec['paths']['/old']['get'];

		self::assertTrue($operation['deprecated']);
	}

	#[Test]
	public function operationWithCustomOperationId(): void
	{
		$spec = $this->generateSpecForController(OperationAttributeController::class);

		$operation = $spec['paths']['/users']['get'];

		self::assertSame('listAllUsers', $operation['operationId']);
	}

	#[Test]
	public function operationSummaryAndDeprecatedTogether(): void
	{
		$spec = $this->generateSpecForController(OperationAttributeController::class);

		$operation = $spec['paths']['/old']['get'];

		self::assertSame('Legacy endpoint', $operation['summary']);
		self::assertTrue($operation['deprecated']);
		self::assertSame('getLegacy', $operation['operationId']);
	}

	#[Test]
	public function operationWithoutOptionalFieldsOmitsThem(): void
	{
		$spec = $this->generateSpecForController(OperationAttributeController::class);

		$operation = $spec['paths']['/simple']['get'];

		self::assertArrayNotHasKey('summary', $operation);
		self::assertArrayNotHasKey('deprecated', $operation);
	}

	// ==================== ADDITIONAL PROPERTIES TESTS ====================

	#[Test]
	public function additionalPropertiesBoolInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'metadata');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/metadata');

		$response = new EndpointResponse('200', 'Metadata response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'additionalProperties' => true,
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/metadata']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertTrue($responseSchema['additionalProperties']);
	}

	#[Test]
	public function additionalPropertiesSchemaInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'dictionary');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/dictionary');

		$response = new EndpointResponse('200', 'Dictionary response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'additionalProperties' => [
						'type' => 'string',
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/dictionary']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertSame(['type' => 'string'], $responseSchema['additionalProperties']);
	}

	#[Test]
	public function additionalPropertiesWithRefInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'map');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/map');

		$response = new EndpointResponse('200', 'Map response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'additionalProperties' => [
						'$ref' => '#/components/schemas/TagDto',
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/map']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertSame(['$ref' => '#/components/schemas/TagDto'], $responseSchema['additionalProperties']);
	}

	#[Test]
	public function enumInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'status');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/status');

		$response = new EndpointResponse('200', 'Status response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'string',
					'enum' => ['active', 'inactive', 'pending'],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/status']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('string', $responseSchema['type']);
		self::assertSame(['active', 'inactive', 'pending'], $responseSchema['enum']);
	}

	#[Test]
	public function additionalPropertiesFalseInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'strict');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/strict');

		$response = new EndpointResponse('200', 'Strict object');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'name' => ['type' => 'string'],
					],
					'additionalProperties' => false,
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/strict']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertFalse($responseSchema['additionalProperties']);
	}

	#[Test]
	public function additionalPropertiesViaJsonContentAttribute(): void
	{
		$spec = $this->generateSpecForController(AdditionalPropertiesController::class);

		$responseSchema = $spec['paths']['/dictionary']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertSame(['type' => 'string'], $responseSchema['additionalProperties']);
	}

	#[Test]
	public function nullableInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'nullable');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/nullable');

		$response = new EndpointResponse('200', 'Nullable response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'string',
					'nullable' => true,
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/nullable']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('string', $responseSchema['type']);
		self::assertTrue($responseSchema['nullable']);
	}

	#[Test]
	public function titleInContentSpec(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'titled');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/titled');

		$response = new EndpointResponse('200', 'Titled response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'title' => 'MyObject',
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/titled']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame('object', $responseSchema['type']);
		self::assertSame('MyObject', $responseSchema['title']);
	}

	// ==================== BUILD SCHEMA FROM SPEC - CONSTRAINT FIELDS ====================

	#[Test]
	public function buildSchemaFromSpecPassesMinimumMaximum(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'constraints');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/constraints');

		$response = new EndpointResponse('200', 'Constrained response');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'age' => [
							'type' => 'integer',
							'minimum' => 0,
							'maximum' => 150,
						],
						'score' => [
							'type' => 'number',
							'minimum' => 0.0,
							'maximum' => 100.0,
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$props = $specArray['paths']['/constraints']['get']['responses']['200']['content']['application/json']['schema']['properties'];

		self::assertSame(0, $props['age']['minimum']);
		self::assertSame(150, $props['age']['maximum']);
		self::assertSame(0, $props['score']['minimum']);
		self::assertSame(100, $props['score']['maximum']);
	}

	#[Test]
	public function buildSchemaFromSpecPassesStringConstraints(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'stringConstraints');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/string-constraints');

		$response = new EndpointResponse('200', 'String constrained');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'code' => [
							'type' => 'string',
							'minLength' => 3,
							'maxLength' => 10,
							'pattern' => '^[A-Z]+$',
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$codeProp = $specArray['paths']['/string-constraints']['get']['responses']['200']['content']['application/json']['schema']['properties']['code'];

		self::assertSame(3, $codeProp['minLength']);
		self::assertSame(10, $codeProp['maxLength']);
		self::assertSame('^[A-Z]+$', $codeProp['pattern']);
	}

	#[Test]
	public function buildSchemaFromSpecPassesDefaultAndDeprecated(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'defaults');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/defaults');

		$response = new EndpointResponse('200', 'Defaults');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'status' => [
							'type' => 'string',
							'default' => 'active',
							'deprecated' => true,
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$statusProp = $specArray['paths']['/defaults']['get']['responses']['200']['content']['application/json']['schema']['properties']['status'];

		self::assertSame('active', $statusProp['default']);
		self::assertTrue($statusProp['deprecated']);
	}

	#[Test]
	public function buildSchemaFromSpecPassesReadOnlyWriteOnly(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'readWrite');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/read-write');

		$response = new EndpointResponse('200', 'ReadWrite');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'readOnly' => true,
						],
						'password' => [
							'type' => 'string',
							'writeOnly' => true,
						],
					],
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$props = $specArray['paths']['/read-write']['get']['responses']['200']['content']['application/json']['schema']['properties'];

		self::assertTrue($props['id']['readOnly']);
		self::assertTrue($props['password']['writeOnly']);
	}

	#[Test]
	public function buildSchemaFromSpecPassesArrayConstraints(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'arrayConstraints');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/array-constraints');

		$response = new EndpointResponse('200', 'Array constrained');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'array',
					'items' => ['type' => 'string'],
					'minItems' => 1,
					'maxItems' => 50,
				],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseSchema = $specArray['paths']['/array-constraints']['get']['responses']['200']['content']['application/json']['schema'];

		self::assertSame(1, $responseSchema['minItems']);
		self::assertSame(50, $responseSchema['maxItems']);
	}

	// ==================== EXAMPLES PASSTHROUGH TESTS ====================

	#[Test]
	public function responseExamplesFromJsonContentPassThrough(): void
	{
		$spec = $this->generateSpecForController(ExamplesController::class);

		$responseContent = $spec['paths']['/with-examples']['get']['responses']['200']['content']['application/json'];

		// Examples should be present at media type level
		self::assertArrayHasKey('examples', $responseContent);
		self::assertArrayHasKey('success', $responseContent['examples']);
		self::assertSame('Successful response', $responseContent['examples']['success']['summary']);
	}

	#[Test]
	public function responseExampleFieldFromContentSpecPassThrough(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'example');
		$endpoint->addMethod('GET');
		$endpoint->setMask('/with-example');

		$response = new EndpointResponse('200', 'With example');
		$response->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'name' => ['type' => 'string'],
					],
				],
				'example' => ['name' => 'John'],
			],
		]);
		$endpoint->addResponse($response);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$responseContent = $specArray['paths']['/with-example']['get']['responses']['200']['content']['application/json'];

		self::assertArrayHasKey('example', $responseContent);
		self::assertSame(['name' => 'John'], $responseContent['example']);
	}

	#[Test]
	public function requestBodyExamplesFromContentSpecPassThrough(): void
	{
		$config = new OpenApiConfig(title: 'Test API', version: '1.0.0');
		$generator = new OpenApiGenerator($config);

		$endpoint = new Endpoint('App\\Controller\\TestController', 'create');
		$endpoint->addMethod('POST');
		$endpoint->setMask('/items');

		$requestBody = new EndpointRequestBody();
		$requestBody->setRequired(true);
		$requestBody->setContentSpec([
			'application/json' => [
				'schema' => [
					'type' => 'object',
					'properties' => [
						'name' => ['type' => 'string'],
					],
				],
				'examples' => [
					'basic' => [
						'summary' => 'Basic item',
						'value' => ['name' => 'Test'],
					],
				],
			],
		]);
		$endpoint->setRequestBody($requestBody);

		$schema = new Schema();
		$schema->addEndpoint($endpoint);

		$spec = $generator->generate($schema);
		$specArray = json_decode(json_encode($spec), true);

		$requestContent = $specArray['paths']['/items']['post']['requestBody']['content']['application/json'];

		self::assertArrayHasKey('examples', $requestContent);
		self::assertArrayHasKey('basic', $requestContent['examples']);
		self::assertSame('Basic item', $requestContent['examples']['basic']['summary']);
	}

	// ==================== HELPER METHODS ====================

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

		return json_decode(json_encode($spec), true);
	}

}

// ==================== TEST DTOS ====================

class UserDto
{

	public int $id;

	public string $name;

	public string $email;

}

class TagDto
{

	public int $id;

	public string $name;

}

class ProductDto
{

	public int $id;

	public string $name;

	public float $price;

}

class ServiceDto
{

	public int $id;

	public string $name;

	public int $durationMinutes;

}

class SuccessDto
{

	public bool $success;

	public string $message;

}

class AcceptedDto
{

	public string $jobId;

	public string $status;

}

class ErrorDto
{

	public string $error;

	public int $code;

}

class ProcessResultDto
{

	public string $result;

}

// ==================== TEST CONTROLLERS ====================

class FileDownloadController implements Controller
{

	#[Get(path: '/download/pdf')]
	#[FileResponseAttribute(contentType: 'application/pdf', description: 'PDF report')]
	public function downloadPdf(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

	#[Get(path: '/download/excel')]
	#[FileResponseAttribute(contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]
	public function downloadExcel(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

	#[Get(path: '/download/image')]
	#[FileResponseAttribute(contentType: 'image/png', filename: 'chart.png')]
	public function downloadImage(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class FileUploadController implements Controller
{

	#[Post(path: '/upload/single')]
	#[FileUpload(name: 'file', required: true)]
	public function uploadSingle(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

	#[Post(path: '/upload/multiple')]
	#[FileUpload(name: 'files', multiple: true)]
	public function uploadMultiple(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

	#[Post(path: '/upload/mixed')]
	#[FileUpload(name: 'avatar', required: true, description: 'Profile picture')]
	#[FileUpload(name: 'documents', multiple: true, required: false)]
	public function uploadMixed(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class PolymorphicResponseController implements Controller
{

	#[Get(path: '/items')]
	#[Response(
		response: 200,
		description: 'List of products or services',
		content: new JsonContent(
			type: 'array',
			items: new Items(
				oneOf: [
					ProductDto::class,
					ServiceDto::class,
				],
			),
		),
	)]
	public function listItems(ApiRequest $request): array
	{
		return [];
	}

	#[Get(path: '/flexible')]
	#[Response(
		response: 200,
		description: 'Flexible list',
		content: new JsonContent(
			type: 'array',
			items: new Items(
				anyOf: [
					ProductDto::class,
					ServiceDto::class,
				],
			),
		),
	)]
	public function listFlexible(ApiRequest $request): array
	{
		return [];
	}

}

class CombinedController implements Controller
{

	#[Post(path: '/process')]
	#[FileUpload(name: 'document', required: true)]
	#[Response(
		response: 200,
		description: 'Processing result',
		content: new JsonContent(
			oneOf: [
				ProcessResultDto::class,
				ErrorDto::class,
			],
		),
	)]
	public function process(ApiRequest $request): array
	{
		return [];
	}

}

class MultiResponseController implements Controller
{

	#[Post(path: '/action')]
	#[Response(response: 200, ref: SuccessDto::class, description: 'Success')]
	#[Response(response: 202, ref: AcceptedDto::class, description: 'Accepted for processing')]
	#[Response(response: 400, ref: ErrorDto::class, description: 'Bad request')]
	public function action(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class DateTimeParameterController implements Controller
{

	#[Get(path: '/applications/download')]
	#[Response(
		response: 200,
		description: 'List of applications or tombstones',
		content: new JsonContent(
			type: 'array',
			items: new Items(oneOf: [
				ProductDto::class,
				ServiceDto::class,
			]),
		),
	)]
	public function download(DateTimeImmutable|null $since = null): ApiResponse
	{
		return new ApiResponse();
	}

	#[Get(path: '/events')]
	public function events(DateTimeImmutable $from, DateTimeImmutable|null $to = null): ApiResponse
	{
		return new ApiResponse();
	}

}

class AdditionalPropertiesController implements Controller
{

	#[Get(path: '/dictionary')]
	#[Response(
		response: 200,
		description: 'Dictionary of values',
		content: new JsonContent(
			type: 'object',
			additionalProperties: new AdditionalProperties(type: 'string'),
		),
	)]
	public function dictionary(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class ExamplesController implements Controller
{

	#[Get(path: '/with-examples')]
	#[Response(
		response: 200,
		description: 'Response with examples',
		content: new JsonContent(
			type: 'object',
			properties: [
				new Property(property: 'name', type: 'string'),
			],
			examples: [
				new Examples(
					example: 'success',
					summary: 'Successful response',
					value: ['name' => 'John'],
				),
			],
		),
	)]
	public function withExamples(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}

class OperationAttributeController implements Controller
{

	#[Get(path: '/users', summary: 'List all users', operationId: 'listAllUsers')]
	public function list(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

	#[Get(path: '/old', summary: 'Legacy endpoint', operationId: 'getLegacy', deprecated: true)]
	public function legacy(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

	#[Get(path: '/simple')]
	public function simple(ApiRequest $request): ApiResponse
	{
		return new ApiResponse();
	}

}
