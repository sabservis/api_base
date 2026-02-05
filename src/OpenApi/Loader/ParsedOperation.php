<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

/**
 * Result of parsing operation attributes from a controller method.
 */
final class ParsedOperation
{

	/**
	 * @param array<string> $httpMethods
	 * @param array<string, mixed> $tags
	 * @param array<string, array<mixed>> $parameters
	 * @param array<mixed>|null $requestBody
	 * @param array<string, array<mixed>> $responses
	 */
	public function __construct(
		public array $httpMethods = [],
		public string $path = '',
		public string|null $id = null,
		public string|null $summary = null,
		public bool $deprecated = false,
		public array $tags = [],
		public array $parameters = [],
		public array|null $requestBody = null,
		public array $responses = [],
	)
	{
	}

}
