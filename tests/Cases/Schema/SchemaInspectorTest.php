<?php declare(strict_types = 1);

namespace Tests\Cases\Adjuster;

require_once __DIR__ . '/../../bootstrap.php';

use QaData\Psr7\Psr7ServerRequestFactory;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointHandler;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Schema\SchemaInspector;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class SchemaInspectorTest extends TestCase
{

	public function testGetEndpointsByTagEmpty(): void
	{
		$schema = new Schema();

		$handler = new EndpointHandler('class', 'method');

		$e1 = new Endpoint($handler);
		$e1->addTag('bar', 'bar1');
		$schema->addEndpoint($e1);

		$inspector = new SchemaInspector($schema);

		Assert::same([], $inspector->getEndpointsByTag('foo'));
	}

	public function testGetEndpointsByTagByName(): void
	{
		$schema = new Schema();

		$handler = new EndpointHandler('class', 'method');

		$e1 = new Endpoint($handler);
		$e1->addTag('foo', 'foo1');
		$schema->addEndpoint($e1);

		$e2 = new Endpoint($handler);
		$e2->addTag('foo', 'foo2');
		$schema->addEndpoint($e2);

		$e3 = new Endpoint($handler);
		$e3->addTag('foo', 'foo3');
		$schema->addEndpoint($e3);

		$e4 = new Endpoint($handler);
		$e4->addTag('bar', 'bar1');
		$schema->addEndpoint($e4);

		$inspector = new SchemaInspector($schema);

		Assert::same([$e1, $e2, $e3], $inspector->getEndpointsByTag('foo'));
	}

	public function testGetEndpointsByTagByNameAndValue(): void
	{
		$schema = new Schema();

		$handler = new EndpointHandler('class', 'method');

		$e1 = new Endpoint($handler);
		$e1->addTag('foo', 'foo1');
		$schema->addEndpoint($e1);

		$e2 = new Endpoint($handler);
		$e2->addTag('foo', 'foo2');
		$schema->addEndpoint($e2);

		$e3 = new Endpoint($handler);
		$e3->addTag('foo', 'foo3');
		$schema->addEndpoint($e3);

		$e4 = new Endpoint($handler);
		$e4->addTag('bar', 'bar1');
		$schema->addEndpoint($e4);

		$inspector = new SchemaInspector($schema);

		Assert::same([$e1], $inspector->getEndpointsByTag('foo', 'foo1'));
	}

}

$test = new SchemaInspectorTest();
$test->run();

