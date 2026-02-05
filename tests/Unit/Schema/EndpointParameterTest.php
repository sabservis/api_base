<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Schema\EndpointParameter;

final class EndpointParameterTest extends TestCase
{

	#[Test]
	public function constructorSetsNameAndType(): void
	{
		$param = new EndpointParameter('userId', 'integer');

		self::assertSame('userId', $param->getName());
		self::assertSame('integer', $param->getType());
	}

	#[Test]
	public function defaultTypeIsString(): void
	{
		$param = new EndpointParameter('name');

		self::assertSame(EndpointParameter::TypeString, $param->getType());
	}

	#[Test]
	public function defaultInIsPath(): void
	{
		$param = new EndpointParameter('id');

		self::assertSame(EndpointParameter::InPath, $param->getIn());
	}

	#[Test]
	public function defaultRequiredIsTrue(): void
	{
		$param = new EndpointParameter('id');

		self::assertTrue($param->isRequired());
	}

	#[Test]
	public function defaultDeprecatedIsFalse(): void
	{
		$param = new EndpointParameter('id');

		self::assertFalse($param->isDeprecated());
	}

	#[Test]
	public function defaultAllowEmptyValueIsFalse(): void
	{
		$param = new EndpointParameter('id');

		self::assertFalse($param->isAllowEmptyValue());
	}

	#[Test]
	public function defaultDescriptionIsNull(): void
	{
		$param = new EndpointParameter('id');

		self::assertNull($param->getDescription());
	}

	#[Test]
	public function setDescriptionUpdatesValue(): void
	{
		$param = new EndpointParameter('id');
		$param->setDescription('User identifier');

		self::assertSame('User identifier', $param->getDescription());
	}

	#[Test]
	public function setDescriptionToNullClearsValue(): void
	{
		$param = new EndpointParameter('id');
		$param->setDescription('Some description');
		$param->setDescription(null);

		self::assertNull($param->getDescription());
	}

	#[Test]
	public function setInToQuery(): void
	{
		$param = new EndpointParameter('filter');
		$param->setIn(EndpointParameter::InQuery);

		self::assertSame(EndpointParameter::InQuery, $param->getIn());
	}

	#[Test]
	public function setInToHeader(): void
	{
		$param = new EndpointParameter('apiKey');
		$param->setIn(EndpointParameter::InHeader);

		self::assertSame(EndpointParameter::InHeader, $param->getIn());
	}

	#[Test]
	public function setInToCookie(): void
	{
		$param = new EndpointParameter('session');
		$param->setIn(EndpointParameter::InCookie);

		self::assertSame(EndpointParameter::InCookie, $param->getIn());
	}

	#[Test]
	public function setRequiredToFalse(): void
	{
		$param = new EndpointParameter('filter');
		$param->setRequired(false);

		self::assertFalse($param->isRequired());
	}

	#[Test]
	public function setDeprecatedToTrue(): void
	{
		$param = new EndpointParameter('oldParam');
		$param->setDeprecated(true);

		self::assertTrue($param->isDeprecated());
	}

	#[Test]
	public function setAllowEmptyValueToTrue(): void
	{
		$param = new EndpointParameter('optionalValue');
		$param->setAllowEmptyValue(true);

		self::assertTrue($param->isAllowEmptyValue());
	}

	#[Test]
	public function typeConstantsExist(): void
	{
		self::assertSame('string', EndpointParameter::TypeString);
		self::assertSame('int', EndpointParameter::TypeInteger);
		self::assertSame('float', EndpointParameter::TypeFloat);
		self::assertSame('boolean', EndpointParameter::TypeBoolean);
		self::assertSame('datetime', EndpointParameter::TypeDateTime);
	}

	#[Test]
	public function inConstantsExist(): void
	{
		self::assertSame('query', EndpointParameter::InQuery);
		self::assertSame('cookie', EndpointParameter::InCookie);
		self::assertSame('header', EndpointParameter::InHeader);
		self::assertSame('path', EndpointParameter::InPath);
	}

	#[Test]
	public function defaultSchemaSpecIsNull(): void
	{
		$param = new EndpointParameter('id');

		self::assertNull($param->getSchemaSpec());
	}

	#[Test]
	public function setSchemaSpecUpdatesValue(): void
	{
		$param = new EndpointParameter('status');
		$schemaSpec = [
			'type' => 'string',
			'enum' => ['active', 'inactive', 'pending'],
		];
		$param->setSchemaSpec($schemaSpec);

		self::assertSame($schemaSpec, $param->getSchemaSpec());
	}

	#[Test]
	public function setSchemaSpecToNullClearsValue(): void
	{
		$param = new EndpointParameter('id');
		$param->setSchemaSpec(['type' => 'integer']);
		$param->setSchemaSpec(null);

		self::assertNull($param->getSchemaSpec());
	}

	#[Test]
	public function defaultSchemaRefIsNull(): void
	{
		$param = new EndpointParameter('id');

		self::assertNull($param->getSchemaRef());
	}

	#[Test]
	public function setSchemaRefUpdatesValue(): void
	{
		$param = new EndpointParameter('type');
		$param->setSchemaRef('App\\Enum\\ItemType');

		self::assertSame('App\\Enum\\ItemType', $param->getSchemaRef());
	}

	#[Test]
	public function setSchemaRefToNullClearsValue(): void
	{
		$param = new EndpointParameter('type');
		$param->setSchemaRef('App\\Enum\\ItemType');
		$param->setSchemaRef(null);

		self::assertNull($param->getSchemaRef());
	}

	#[Test]
	public function defaultExampleIsNull(): void
	{
		$param = new EndpointParameter('id');

		self::assertNull($param->getExample());
	}

	#[Test]
	public function setExampleUpdatesValue(): void
	{
		$param = new EndpointParameter('query');
		$param->setExample('search term');

		self::assertSame('search term', $param->getExample());
	}

	#[Test]
	public function setExampleToNullClearsValue(): void
	{
		$param = new EndpointParameter('query');
		$param->setExample('search term');
		$param->setExample(null);

		self::assertNull($param->getExample());
	}

	#[Test]
	public function exampleSupportsVariousTypes(): void
	{
		$param = new EndpointParameter('data');

		$param->setExample(42);
		self::assertSame(42, $param->getExample());

		$param->setExample(['key' => 'value']);
		self::assertSame(['key' => 'value'], $param->getExample());

		$param->setExample(true);
		self::assertTrue($param->getExample());
	}

	#[Test]
	public function defaultStyleIsNull(): void
	{
		$param = new EndpointParameter('id');

		self::assertNull($param->getStyle());
	}

	#[Test]
	public function setStyleUpdatesValue(): void
	{
		$param = new EndpointParameter('ids');
		$param->setStyle('form');

		self::assertSame('form', $param->getStyle());
	}

	#[Test]
	public function setStyleToNullClearsValue(): void
	{
		$param = new EndpointParameter('ids');
		$param->setStyle('form');
		$param->setStyle(null);

		self::assertNull($param->getStyle());
	}

	#[Test]
	public function defaultExplodeIsNull(): void
	{
		$param = new EndpointParameter('id');

		self::assertNull($param->getExplode());
	}

	#[Test]
	public function setExplodeUpdatesValue(): void
	{
		$param = new EndpointParameter('ids');
		$param->setExplode(true);

		self::assertTrue($param->getExplode());
	}

	#[Test]
	public function setExplodeToNullClearsValue(): void
	{
		$param = new EndpointParameter('ids');
		$param->setExplode(true);
		$param->setExplode(null);

		self::assertNull($param->getExplode());
	}

}
