<?php declare(strict_types = 1);

namespace Tests\Cases\Dispatcher;

require_once __DIR__ . '/../../bootstrap.php';

use QaData\Psr7\Psr7ResponseFactory;
use QaData\Psr7\Psr7ServerRequestFactory;
use Sabservis\Api\Dispatcher\JsonDispatcher;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Tests\Fixtures\Handler\FakeResponseHandler;

Environment::setupFunctions();

final class JsonDispatcherTestNotFinished extends TestCase
{

	public function testMarchedUseHandle(): void
	{
		$request = new ApiRequest(Psr7ServerRequestFactory::fromGlobals());
		$response = new ApiResponse(Psr7ResponseFactory::fromGlobal());

		$dispatcher = new JsonDispatcher(
			router: new FakeRouter(true),
			handler: new FakeResponseHandler(),
			decoratorManager: null,
		);
		Assert::same($response, $dispatcher->dispatch($request, $response));
	}

}

$test = new JsonDispatcherTestNotFinished();
$test->run();
