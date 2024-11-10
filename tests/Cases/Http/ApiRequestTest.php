<?php declare(strict_types = 1);

namespace Tests\Cases\Adjuster;

require_once __DIR__ . '/../../bootstrap.php';

use QaData\Psr7\Psr7ServerRequestFactory;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\RequestAttributes;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class ApiRequestTest extends TestCase
{

	public function testParameters(): void
	{
		$request = Psr7ServerRequestFactory::fromGlobals();
		$apiRequest = new ApiRequest($request);

		Assert::false($apiRequest->hasParameter('name'));
		Assert::false($apiRequest->hasParameter('fake'));
		Assert::equal(null, $apiRequest->getParameter('name'));
		Assert::equal('default', $apiRequest->getParameter('name', 'default'));
		Assert::equal([], $apiRequest->getParameters());
	}

	public function testParametersWithAttribute(): void
	{
		$request = Psr7ServerRequestFactory::fromGlobals();
		$request = $request->withAttribute(RequestAttributes::Parameters, ['name' => 'John Doe', 'title' => null]);
		$apiRequest = new ApiRequest($request);

		Assert::true($apiRequest->hasParameter('name'));
		Assert::true($apiRequest->hasParameter('title'));
		Assert::false($apiRequest->hasParameter('fake'));
		Assert::equal('John Doe', $apiRequest->getParameter('name'));
		Assert::equal(['name' => 'John Doe', 'title' => null], $apiRequest->getParameters());
		Assert::false($apiRequest->hasParameter('company'));
		Assert::equal(null, $apiRequest->getParameter('company'));
		Assert::equal('default', $apiRequest->getParameter('company', 'default'));
	}

}

$test = new ApiRequestTest();
$test->run();

