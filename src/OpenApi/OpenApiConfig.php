<?php declare(strict_types = 1);

namespace Sabservis\Api\OpenApi;

final class OpenApiConfig
{

	/**
	 * @param array<array{url: string, description?: string, variables?: array<string, array{default: string, description?: string, enum?: array<string>}>}> $servers
	 * @param array<string, array<string, mixed>> $securitySchemes
	 * @param array<array<string, array<string>>> $defaultSecurity
	 * @param array<string, string> $tags Tag name => description
	 */
	public function __construct(
		public string $title = 'API',
		public string $version = '1.0.0',
		public string|null $description = null,
		public string|null $termsOfService = null,
		public string|null $contactName = null,
		public string|null $contactEmail = null,
		public string|null $contactUrl = null,
		public string|null $licenseName = null,
		public string|null $licenseUrl = null,
		public array $servers = [],
		public array $securitySchemes = [],
		public array $defaultSecurity = [],
		public array $tags = [],
		public bool $includeDefaultErrorResponse = true,
	)
	{
	}

}
