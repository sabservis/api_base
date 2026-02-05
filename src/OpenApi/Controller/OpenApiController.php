<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Controller;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\OpenApi\Generator\OpenApiGenerator;
use Sabservis\Api\OpenApi\OpenApiConfig;
use Sabservis\Api\Schema\Schema;
use Sabservis\Api\UI\Controller\Controller;

#[Tag(name: 'OpenAPI', description: 'OpenAPI specification endpoints', hidden: true)]
final class OpenApiController implements Controller
{

	public function __construct(
		private Schema $schema,
		private OpenApiConfig $config,
	)
	{
	}

	#[Get(
		path: '/openapi.json',
		operationId: 'getOpenApiSpec',
		summary: 'Get OpenAPI specification in JSON format',
	)]
	public function spec(ApiRequest $request, ApiResponse $response): ApiResponse
	{
		$generator = new OpenApiGenerator($this->config);
		$json = $generator->generateJson($this->schema);

		return $response
			->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->withHeader('Cache-Control', 'public, max-age=3600')
			->writeBody($json);
	}

}
