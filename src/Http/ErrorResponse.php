<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;

/**
 * Standard error response DTO for OpenAPI documentation.
 *
 * Matches the structure produced by ErrorResponseBuilder.
 */
#[Schema(description: 'Standard error response returned by the API')]
final class ErrorResponse
{

	#[Property(description: 'HTTP status code', example: 400)]
	public int $code;

	#[Property(description: 'Error message', example: 'Bad Request')]
	public string $message;

	#[Property(
		description: 'Trace ID for error tracking',
		nullable: true,
		example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
	)]
	public string|null $traceId = null;

	#[Property(type: 'object', description: 'Additional error context', nullable: true)]
	public mixed $context = null;

}
