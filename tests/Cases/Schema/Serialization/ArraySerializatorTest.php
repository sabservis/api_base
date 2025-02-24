<?php declare(strict_types = 1);

namespace Tests\Cases\Schema\Serialization;

require_once __DIR__ . '/../../../bootstrap.php';

use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\SchemaBuilder;
use Sabservis\Api\Schema\Serialization\ArraySerializator;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class ArraySerializatorTest extends TestCase
{

	public function testSuccess(): void
	{
		$serializator = new ArraySerializator();

		$builder = new SchemaBuilder();

		$c1 = $builder->addController('c1-class');
		$c1->setId('c1-id');
		$c1->setPath('c1-path');
		$c1->addTag('c1-t1', 'c1-t1-value');

		$c1->addMethod('m1'); // Skipped, missing path

		$m2 = $c1->addMethod('m2');
		$m2->addHttpMethod(Endpoint::METHOD_GET);
		$m2->addHttpMethod(Endpoint::METHOD_POST);
		$m2->addHttpMethod(Endpoint::METHOD_PUT);
		$m2->setPath('m2-path');

		$m3 = $c1->addMethod('m3');
		$m3->setId('m3-id');
		$m3->addHttpMethod(Endpoint::METHOD_GET);
		$m3->addHttpMethod(Endpoint::METHOD_POST);
		$m3->setPath('m3-path/{m3-p1}');
		$m3->addTag('m3-t1');
		$m3->addTag('m3-t2', 'm3-t2-value');

		$m3rb = new EndpointRequestBody();
		$m3rb->setRequired(true);
		$m3rb->setEntity('A\Class');
		$m3rb->setDescription('description');
		$m3->setRequestBody($m3rb);

		$m3->addResponse('200', 'Success')
			->setEntity('SomeClass[]');
		$m3->addResponse('404', 'Not found');

		$m3p1 = $m3->addParameter('m3-p1', EndpointParameter::TypeInteger);
		$m3p1->setDescription('m3-p1-desc');
		$m3p1->setIn(EndpointParameter::InPath);
		$m3p1->setRequired(true);
		$m3p1->setAllowEmpty(true);
		$m3p1->setDeprecated(true);

		$m3p2 = $m3->addParameter('m3-p2', EndpointParameter::TypeString);
		$m3p2->setIn(EndpointParameter::InQuery);

		$expected = [
			[
				'attributes' => ['pattern' => '/c1-path/m2-path'],
				'handler' => ['class' => 'c1-class', 'method' => 'm2'],
				'id' => null,
				'mask' => '/c1-path/m2-path',
				'methods' => ['GET', 'POST', 'PUT'],
				'parameters' => [],
				'responses' => [],
				'tags' => ['c1-t1' => 'c1-t1-value'],
			],
			[
				'attributes' => [
					'pattern' => '/c1-path/m3-path/(?P<m3-p1>[^/]+)',
				],
				'handler' => [
					'class' => 'c1-class',
					'method' => 'm3',
				],
				'id' => 'c1-id.m3-id',
				'mask' => '/c1-path/m3-path/{m3-p1}',
				'methods' => ['GET', 'POST'],
				'parameters' => [
					'm3-p1' => [
						'allowEmpty' => true,
						'denormalizer' => null,
						'deprecated' => true,
						'description' => 'm3-p1-desc',
						'in' => 'path',
						'name' => 'm3-p1',
						'required' => true,
						'type' => 'int',
					],
					'm3-p2' => [
						'allowEmpty' => false,
						'denormalizer' => null,
						'deprecated' => false,
						'description' => null,
						'in' => 'query',
						'name' => 'm3-p2',
						'required' => true,
						'type' => 'string',
					],
				],
				'responses' => [
					200 => [
						'code' => '200',
						'description' => 'Success',
						'entity' => 'SomeClass[]',
					],
					404 => ['code' => '404', 'description' => 'Not found'],
				],
				'tags' => ['c1-t1' => 'c1-t1-value', 'm3-t1' => null, 'm3-t2' => 'm3-t2-value'],
				'requestBody' => [
					'description' => 'description',
					'entity' => 'A\Class',
					'required' => true,
				],
			],
		];

		Assert::same($expected, $serializator->serialize($builder));
	}

	public function testSerialiozeException(): void
	{
		$serializator = new ArraySerializator();

		$builder = new SchemaBuilder();

		$c1 = $builder->addController('c1-class');
		$c1->setId('c1-id');
		$c1->setPath('{c1-p1}/{c1-p1}');

		// Only pairs Controller + Method are validated, so Method must be defined
		$m1 = $c1->addMethod('m1');
		$m1->setPath('{m1-p1}');

		$m1->addParameter('m1-p1', EndpointParameter::InPath);

		Assert::exception(static function () use ($serializator, $builder): void {
			$serializator->serialize($builder);
		}, InvalidStateException::class, 'Duplicate mask parameter "c1-p1" in path "/{c1-p1}/{c1-p1}/{m1-p1}"');
	}

	public function testDuplicateMarkParameterInMethod(): void
	{
		$serializator = new ArraySerializator();

		$builder = new SchemaBuilder();

		$c1 = $builder->addController('c1-class');
		$c1->setId('c1-id');
		$c1->setPath('{c1-p1}');

		$m1 = $c1->addMethod('m1');
		$m1->setPath('{m1-p1}/{m1-p1}');

		Assert::exception(static function () use ($serializator, $builder): void {
			$serializator->serialize($builder);
		}, InvalidStateException::class, 'Duplicate mask parameter "m1-p1" in path "/{c1-p1}/{m1-p1}/{m1-p1}"');
	}

	public function testParameterInMaskIsNotDefinedInPath(): void
	{
		$serializator = new ArraySerializator();

		$builder = new SchemaBuilder();

		$c1 = $builder->addController('c1-class');
		$c1->setId('c1-id');

		$m1 = $c1->addMethod('m1');
		$m1->setPath('path');

		$m1->addParameter('m1-p1', EndpointParameter::InPath);

		Assert::exception(static function () use ($serializator, $builder): void {
			$serializator->serialize($builder);
		}, InvalidStateException::class, '@RequestParameter(name="m1-p1", in=path) is not defined in mask (@Path annotations)');
	}

}

$test = new ArraySerializatorTest();
$test->run();
