<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi\Loader;

/**
 * Result of parsing operation attributes from a controller method.
 */
final class ParsedOperation
{

	/**
	 * Special value indicating security should be inherited from controller.
	 */
	public const SECURITY_INHERIT = '__inherit__';

	/**
	 * @param array<string> $httpMethods
	 * @param array<string, mixed> $tags
	 * @param array<string, array<mixed>> $parameters
	 * @param array<mixed>|null $requestBody
	 * @param array<string, array<mixed>> $responses
	 * @param array<array<string, array<string>>>|string|null $security Security requirements or SECURITY_INHERIT
	 */
	public function __construct(
		public array $httpMethods = [],
		public string $path = '',
		public string|null $id = null,
		public string|null $summary = null,
		public string|null $description = null,
		public bool $deprecated = false,
		public array $tags = [],
		public array $parameters = [],
		public array|null $requestBody = null,
		public array $responses = [],
		public array|string|null $security = self::SECURITY_INHERIT,
	)
	{
	}

}
