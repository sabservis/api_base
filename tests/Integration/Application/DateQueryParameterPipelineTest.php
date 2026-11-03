<?php declare(strict_types = 1);

namespace Tests\Integration\Application;

use DateTimeImmutable;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\QueryParameter;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Dispatcher\ApiDispatcher;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Handler\ServiceHandler;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Mapping\RequestParameterMapping;
use Sabservis\Api\Mapping\Serializer\DataMapperSerializer;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\Loader\OpenApiAttributeLoader;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Router\Router;
use Sabservis\Api\Schema\Schema as ApiSchema;
use Sabservis\Api\Schema\Serialization\ArrayHydrator;
use Sabservis\Api\UI\Controller\Controller;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class DateQueryParameterPipelineTest extends TestCase
{

	/**
	 * @return array<string, array{string}>
	 */
	public static function dateEndpoints(): array
	{
		return [
			'format on attribute' => ['/dates/format'],
			'format in schema' => ['/dates/schema'],
		];
	}

	#[Test]
	#[DataProvider('dateEndpoints')]
	public function explicitDateTypeInjectsDateTimeAtMidnight(string $path): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: $path, queryParams: ['dateFrom' => '2024-01-30']),
			new ApiResponse(),
		);

		self::assertSame('{"dateFrom":"2024-01-30T00:00:00"}', $result->getBody());
	}

	#[Test]
	#[DataProvider('dateEndpoints')]
	public function dateFormatRejectsValueWithTime(string $path): void
	{
		$this->expectException(ClientErrorException::class);
		$this->expectExceptionCode(400);
		$this->expectExceptionMessage(
			"Parameter 'dateFrom': invalid value '2024-01-30T10:00:00'. Expected date (YYYY-MM-DD).",
		);

		$this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: $path, queryParams: ['dateFrom' => '2024-01-30T10:00:00']),
			new ApiResponse(),
		);
	}

	#[Test]
	public function dateFormatRejectsImpossibleDate(): void
	{
		$this->expectException(ClientErrorException::class);

		$this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/dates/format', queryParams: ['dateFrom' => '2024-02-31']),
			new ApiResponse(),
		);
	}

	#[Test]
	public function dateTimeWithoutFormatKeepsAcceptingTime(): void
	{
		$result = $this->createDispatcher()->dispatch(
			new ApiRequest(method: 'GET', uri: '/dates/plain', queryParams: ['dateFrom' => '2024-01-30T10:00:00']),
			new ApiResponse(),
		);

		self::assertSame('{"dateFrom":"2024-01-30T10:00:00"}', $result->getBody());
	}

	#[Test]
	#[DataProvider('dateEndpoints')]
	public function openApiShowsDateFormat(string $path): void
	{
		self::assertSame(['type' => 'string', 'format' => 'date'], $this->parameterSchema($path));
	}

	#[Test]
	public function openApiKeepsDateTimeWithoutFormat(): void
	{
		self::assertSame(['type' => 'string', 'format' => 'date-time'], $this->parameterSchema('/dates/plain'));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parameterSchema(string $path): array
	{
		$spec = (new OpenApiGenerator(new OpenApiConfig(title: 'Test', version: '1')))->generate($this->createSchema());
		$spec = json_decode(json_encode($spec, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

		return $spec['paths'][$path]['get']['parameters'][0]['schema'];
	}

	private function createSchema(): ApiSchema
	{
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinition('dates.controller')->setType(DateQueryController::class);

		return (new ArrayHydrator())->hydrate((new OpenApiAttributeLoader($containerBuilder))->load());
	}

	private function createDispatcher(): ApiDispatcher
	{
		$container = $this->createMock(Container::class);
		$container->method('getByType')->willReturn(new DateQueryController());

		$serializer = new DataMapperSerializer();

		return new ApiDispatcher(
			new Router($this->createSchema()),
			new ServiceHandler($container, $serializer),
			$serializer,
			new RequestParameterMapping(),
		);
	}

}

class DateQueryController implements Controller
{

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/dates/format')]
	#[QueryParameter(name: 'dateFrom', type: DateTimeImmutable::class, format: 'date')]
	public function format(DateTimeImmutable $dateFrom): array
	{
		return ['dateFrom' => $dateFrom->format('Y-m-d\TH:i:s')];
	}

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/dates/schema')]
	#[QueryParameter(
		name: 'dateFrom',
		type: DateTimeImmutable::class,
		schema: new Schema(type: 'string', format: 'date'),
	)]
	public function schema(DateTimeImmutable $dateFrom): array
	{
		return ['dateFrom' => $dateFrom->format('Y-m-d\TH:i:s')];
	}

	/**
	 * @return array<string, string>
	 */
	#[Get(path: '/dates/plain')]
	#[QueryParameter(name: 'dateFrom', type: DateTimeImmutable::class)]
	public function plain(DateTimeImmutable $dateFrom): array
	{
		return ['dateFrom' => $dateFrom->format('Y-m-d\TH:i:s')];
	}

}
