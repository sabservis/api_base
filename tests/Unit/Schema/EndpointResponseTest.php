<?php declare(strict_types = 1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Schema\EndpointResponse;

final class EndpointResponseTest extends TestCase
{

	#[Test]
	public function constructorSetsCodeAndDescription(): void
	{
		$response = new EndpointResponse('200', 'Success');

		self::assertSame('200', $response->getCode());
		self::assertSame('Success', $response->getDescription());
	}

	#[Test]
	public function defaultEntityIsNull(): void
	{
		$response = new EndpointResponse('200', 'OK');

		self::assertNull($response->getEntity());
	}

	#[Test]
	public function setEntityUpdatesValue(): void
	{
		$response = new EndpointResponse('200', 'User returned');
		$response->setEntity('App\\Dto\\UserDto');

		self::assertSame('App\\Dto\\UserDto', $response->getEntity());
	}

	#[Test]
	public function setEntityToNull(): void
	{
		$response = new EndpointResponse('200', 'OK');
		$response->setEntity('App\\Dto\\SomeDto');
		$response->setEntity(null);

		self::assertNull($response->getEntity());
	}

	#[Test]
	public function differentStatusCodes(): void
	{
		$codes = ['200', '201', '204', '400', '401', '403', '404', '422', '500'];

		foreach ($codes as $code) {
			$response = new EndpointResponse($code, 'Description');
			self::assertSame($code, $response->getCode());
		}
	}

	#[Test]
	public function descriptionCanBeEmpty(): void
	{
		$response = new EndpointResponse('204', '');

		self::assertSame('', $response->getDescription());
	}

	#[Test]
	public function descriptionWithSpecialCharacters(): void
	{
		$description = 'User not found. Check the "id" parameter.';
		$response = new EndpointResponse('404', $description);

		self::assertSame($description, $response->getDescription());
	}

	#[Test]
	public function defaultWrapperTypeIsNull(): void
	{
		$response = new EndpointResponse('200', 'OK');

		self::assertNull($response->getWrapperType());
	}

	#[Test]
	public function setWrapperTypeUpdatesValue(): void
	{
		$response = new EndpointResponse('200', 'List of users');
		$response->setWrapperType(EndpointResponse::WrapperListWithMeta);

		self::assertSame(EndpointResponse::WrapperListWithMeta, $response->getWrapperType());
	}

	#[Test]
	public function setWrapperTypeToNull(): void
	{
		$response = new EndpointResponse('200', 'OK');
		$response->setWrapperType(EndpointResponse::WrapperListNoMeta);
		$response->setWrapperType(null);

		self::assertNull($response->getWrapperType());
	}

	#[Test]
	public function isListWithMetaReturnsTrueWhenSet(): void
	{
		$response = new EndpointResponse('200', 'List with meta');
		$response->setWrapperType(EndpointResponse::WrapperListWithMeta);

		self::assertTrue($response->isListWithMeta());
		self::assertFalse($response->isListNoMeta());
	}

	#[Test]
	public function isListNoMetaReturnsTrueWhenSet(): void
	{
		$response = new EndpointResponse('200', 'List without meta');
		$response->setWrapperType(EndpointResponse::WrapperListNoMeta);

		self::assertTrue($response->isListNoMeta());
		self::assertFalse($response->isListWithMeta());
	}

	#[Test]
	public function isListMethodsReturnFalseWhenWrapperTypeIsNull(): void
	{
		$response = new EndpointResponse('200', 'OK');

		self::assertFalse($response->isListWithMeta());
		self::assertFalse($response->isListNoMeta());
	}

	#[Test]
	public function wrapperTypeConstants(): void
	{
		self::assertSame('list_with_meta', EndpointResponse::WrapperListWithMeta);
		self::assertSame('list_no_meta', EndpointResponse::WrapperListNoMeta);
	}

	#[Test]
	public function setEntityWithArray(): void
	{
		$response = new EndpointResponse('200', 'Mixed list');
		$entities = ['App\\Dto\\ArticleDto', 'App\\Dto\\VideoDto'];
		$response->setEntity($entities);

		self::assertSame($entities, $response->getEntity());
	}

	#[Test]
	public function isEntityOneOfReturnsTrueForArray(): void
	{
		$response = new EndpointResponse('200', 'Mixed list');
		$response->setEntity(['App\\Dto\\ArticleDto', 'App\\Dto\\VideoDto']);

		self::assertTrue($response->isEntityOneOf());
	}

	#[Test]
	public function isEntityOneOfReturnsFalseForString(): void
	{
		$response = new EndpointResponse('200', 'Single type');
		$response->setEntity('App\\Dto\\UserDto');

		self::assertFalse($response->isEntityOneOf());
	}

	#[Test]
	public function isEntityOneOfReturnsFalseForNull(): void
	{
		$response = new EndpointResponse('200', 'No entity');

		self::assertFalse($response->isEntityOneOf());
	}

}
