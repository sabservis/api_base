<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;

final class RequestParameterMappingTest extends TestCase
{

	private RequestParameterMapping $mapping;

	protected function setUp(): void
	{
		$this->mapping = new RequestParameterMapping();
	}

	#[Test]
	public function mapWithoutEndpointThrows(): void
	{
		$request = new ApiRequest(method: 'GET', uri: '/');
		$this->expectException(InvalidStateException::class);

		$this->mapping->map($request);
	}

	#[Test]
	public function mapWithNoParameters(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$result = $this->mapping->map($request);

		self::assertInstanceOf(ApiRequest::class, $result);
	}

	#[Test]
	public function mapPathParameterInteger(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('id', EndpointParameter::TypeInteger);
		$param->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/users/42');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => '42']);
		$result = $this->mapping->map($request);

		self::assertSame(42, $result->getAttribute(RequestAttributes::Parameters->value)['id']);
	}

	#[Test]
	public function mapPathParameterFloat(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('price', EndpointParameter::TypeFloat);
		$param->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['price' => '19.99']);
		$result = $this->mapping->map($request);

		self::assertSame(19.99, $result->getAttribute(RequestAttributes::Parameters->value)['price']);
	}

	#[Test]
	public function mapPathParameterBoolean(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('active', EndpointParameter::TypeBoolean);
		$param->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['active' => 'true']);
		$result = $this->mapping->map($request);

		self::assertTrue($result->getAttribute(RequestAttributes::Parameters->value)['active']);
	}

	#[Test]
	public function mapQueryParameter(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('page', EndpointParameter::TypeInteger);
		$param->setIn(EndpointParameter::InQuery);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['page' => '5']);
		$result = $this->mapping->map($request);

		self::assertSame(5, $result->getAttribute(RequestAttributes::Parameters->value)['page']);
	}

	#[Test]
	public function mapHeaderParameter(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('X-Api-Key', EndpointParameter::TypeString);
		$param->setIn(EndpointParameter::InHeader);
		$endpoint->addParameter($param);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			headers: ['x-api-key' => 'secret123'],
		);
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$result = $this->mapping->map($request);

		self::assertSame('secret123', $result->getHeader('x-api-key'));
	}

	#[Test]
	public function mapCookieParameter(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('session', EndpointParameter::TypeString);
		$param->setIn(EndpointParameter::InCookie);
		$endpoint->addParameter($param);

		$request = new ApiRequest(
			method: 'GET',
			uri: '/',
			cookies: ['session' => 'abc123'],
		);
		$request = $request->withAttribute(RequestAttributes::Endpoint->value, $endpoint);
		$result = $this->mapping->map($request);

		self::assertSame('abc123', $result->getCookie('session'));
	}

	#[Test]
	public function mapRequiredParameterMissingThrows(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('id', EndpointParameter::TypeInteger);
		$param->setIn(EndpointParameter::InPath);
		$param->setRequired(true);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, []);
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Path request parameter "id" should be provided.');

		$this->mapping->map($request);
	}

	#[Test]
	public function mapOptionalParameterMissing(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('page', EndpointParameter::TypeInteger);
		$param->setIn(EndpointParameter::InQuery);
		$param->setRequired(false);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, []);
		$result = $this->mapping->map($request);

		self::assertInstanceOf(ApiRequest::class, $result);
	}

	#[Test]
	public function mapEmptyValueNotAllowedThrows(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('name', EndpointParameter::TypeString);
		$param->setIn(EndpointParameter::InPath);
		$param->setAllowEmptyValue(false);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['name' => '']);
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage('Path request parameter "name" should not be empty.');

		$this->mapping->map($request);
	}

	#[Test]
	public function mapInvalidIntegerThrows(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('id', EndpointParameter::TypeInteger);
		$param->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => 'not-a-number']);
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Parameter 'id': invalid value 'not-a-number'. Expected integer.");

		$this->mapping->map($request);
	}

	#[Test]
	public function mapDateTimeParameter(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('date', DateTimeInterface::class);
		$param->setIn(EndpointParameter::InQuery);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['date' => '2024-01-15']);
		$result = $this->mapping->map($request);
		$dateValue = $result->getAttribute(RequestAttributes::Parameters->value)['date'];

		self::assertInstanceOf(DateTimeImmutable::class, $dateValue);
		self::assertSame('2024-01-15', $dateValue->format('Y-m-d'));
	}

	#[Test]
	public function mapInvalidIntegerShowsProvidedValue(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('id', EndpointParameter::TypeInteger);
		$param->setIn(EndpointParameter::InPath);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['id' => 'abc123']);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Parameter 'id': invalid value 'abc123'. Expected integer.");

		$this->mapping->map($request);
	}

	#[Test]
	public function mapInvalidFloatShowsProvidedValue(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('price', EndpointParameter::TypeFloat);
		$param->setIn(EndpointParameter::InQuery);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['price' => 'not-a-number']);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage("Parameter 'price': invalid value 'not-a-number'. Expected number (e.g. 3.14).");

		$this->mapping->map($request);
	}

	#[Test]
	public function mapInvalidDateTimeShowsProvidedValue(): void
	{
		$endpoint = new Endpoint('TestController', 'testMethod');

		$param = new EndpointParameter('date', DateTimeInterface::class);
		$param->setIn(EndpointParameter::InQuery);
		$endpoint->addParameter($param);

		$request = new ApiRequest(method: 'GET', uri: '/');
		$request = $request
			->withAttribute(RequestAttributes::Endpoint->value, $endpoint)
			->withAttribute(RequestAttributes::Parameters->value, ['date' => 'invalid-date']);

		$this->expectException(ClientErrorException::class);
		$this->expectExceptionMessage(
			"Parameter 'date': invalid value 'invalid-date'. Expected date/datetime (e.g. 2024-01-30 or 2024-01-30T15:30:00).",
		);

		$this->mapping->map($request);
	}

}
