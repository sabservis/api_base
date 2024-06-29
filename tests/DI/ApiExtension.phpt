<?php declare(strict_types = 1);

require_once __DIR__ . '/../bootstrap.php';

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Sabservis\Api\Application\Application;
use Sabservis\Api\DI\ApiExtension;
use Sabservis\Api\Dispatcher\JsonDispatcher;
use Sabservis\Api\Schema\Schema;
use Tester\Assert;

Tester\Environment::setupFunctions();

test('', function (): void {
	$loader = new ContainerLoader(Environment::getTestDir(), true);
	$class = $loader->load(function (Compiler $compiler): void {
		$compiler->addExtension('api', new ApiExtension());
		$compiler->addConfig([
			'parameters' => [
				'debugMode' => true,
			],
		]);
	}, 1);

	/** @var Container $container */
	$container = new $class();

	Assert::type(JsonDispatcher::class, $container->getService('sabservisApi.dispatcher'));
	Assert::type(Schema::class, $container->getService('sabservisApi.schema'));
	Assert::type(Application::class, $container->getService('sabservisApi.application'));
});
