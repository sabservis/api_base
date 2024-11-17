<?php declare(strict_types = 1);

namespace Tests\Cases\DI;

require_once __DIR__ . '/../../bootstrap.php';

use Environment;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Sabservis\Api\Application\Application;
use Sabservis\Api\Decorator\DecoratorManager;
use Sabservis\Api\Decorator\RequestParametersDecorator;
use Sabservis\Api\DI\ApiExtension;
use Sabservis\Api\Dispatcher\Dispatcher;
use Sabservis\Api\ErrorHandler\ErrorHandler;
use Sabservis\Api\Handler\Handler;
use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;
use Sabservis\Api\Mapping\Normalizer\Processor\NormalizerProcessor;
use Sabservis\Api\Mapping\Normalizer\Types;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\EntitySerializer;
use Sabservis\Api\Mapping\Validator\EntityValidator;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\Utils\ChainBuilder;
use Tester\Assert;
use Tester\TestCase;
use function assert;

Environment::setupFunctions();

final class ApiExtensionTest extends TestCase
{

	public function testDefault(): void
	{
		$loader = new ContainerLoader(Environment::getTmpDir(), true);
		$class = $loader->load(static function (Compiler $compiler): void {
			$compiler->addExtension('api', new ApiExtension());
			$compiler->addConfig([
				'parameters' => [
					'productionMode' => false,
				],
			]);
		}, 1);

		$container = new $class();
		assert($container instanceof Container);
		Assert::type(ChainBuilder::class, $container->getService('api.middleware.chain'));
		Assert::type(Dispatcher::class, $container->getService('api.dispatcher'));
		Assert::type(Schema::class, $container->getService('api.schema'));
		Assert::type(Application::class, $container->getService('api.application'));
		Assert::type(Router::class, $container->getService('api.router'));
		Assert::type(Handler::class, $container->getService('api.handler'));
		Assert::type(DecoratorManager::class, $container->getService('api.decorator.manager'));
		Assert::type(RequestParametersDecorator::class, $container->getService('api.decorator.request.parameters'));
		Assert::type(RequestParameterMapping::class, $container->getService('api.request.parameters.mapping'));
		Assert::type(EntityValidator::class, $container->getService('api.request.entity.validator'));
		Assert::type(NormalizerProvider::class, $container->getService('api.request.entity.normalizer.typeProvider'));
		Assert::type(NormalizerProcessor::class, $container->getService('api.request.entity.normalizer'));
		Assert::type(EntitySerializer::class, $container->getService('api.request.entity.serializer'));
		Assert::type(ErrorHandler::class, $container->getService('api.errorHandler'));
	}

	public function testNormalizerRegistrations(): void
	{
		$loader = new ContainerLoader(Environment::getTmpDir(), true);
		$class = $loader->load(static function (Compiler $compiler): void {
			$compiler->addExtension('api', new ApiExtension());
			$compiler->addConfig([
				'parameters' => [
					'productionMode' => false,
				],
				'api' => [
					'normalizer' => [
						'typeProvider' => [
							'types' => [
								Types\ArrayTypeNormalizer::class,
								Types\BooleanTypeNormalizer::class,
								Types\IntegerTypeNormalizer::class,
							],
						],
					],
				],
			]);
		}, 2);

		$container = new $class();
		$provider = $container->getService('api.request.entity.normalizer.typeProvider');
		assert($provider instanceof NormalizerProvider);

		Assert::type(Types\ArrayTypeNormalizer::class, $provider->findNormalizer('array'));
		Assert::type(Types\BooleanTypeNormalizer::class, $provider->findNormalizer('bool'));
		Assert::type(Types\IntegerTypeNormalizer::class, $provider->findNormalizer('int'));
		Assert::null($provider->findNormalizer('string'));
	}

}

$test = new ApiExtensionTest();
$test->run();
