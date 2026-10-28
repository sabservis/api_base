<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use function end;
use function explode;
use function ltrim;
use function str_contains;

/**
 * Trait for handling class name resolution in OpenAPI attributes.
 *
 * Provides utilities for extracting short class names from fully-qualified names
 * and detecting if a string looks like a class name.
 *
 * Used by: Items, JsonContent, Schema, Property, AdditionalProperties, RequestBody, Response
 */
trait ClassNameSupport
{

	/**
	 * Extract short class name from fully-qualified class name.
	 *
	 * Example: 'App\Dto\UserDto' -> 'UserDto'
	 */
	protected function getShortClassName(string $className): string
	{
		$parts = explode('\\', $className);

		return end($parts);
	}

	/**
	 * Check if a string looks like a fully-qualified class name.
	 *
	 * A class name should contain a namespace separator (backslash).
	 * This is used instead of class_exists() because during DI compilation
	 * classes might not be autoloaded yet.
	 */
	protected function looksLikeClassName(string $value): bool
	{
		return str_contains($value, '\\');
	}

	/**
	 * Resolve a reference to its short name.
	 *
	 * If the reference looks like a fully-qualified class name, returns the short name.
	 * Otherwise, returns the reference as-is.
	 */
	protected function resolveRefName(string|null $ref): string
	{
		if ($ref === null) {
			return '';
		}

		if ($this->looksLikeClassName($ref)) {
			return $this->getShortClassName($ref);
		}

		return $ref;
	}

	/**
	 * Build a $ref spec. A class reference also carries the class name (`_class`) so the generator
	 * can tell apart classes sharing a short name; the marker never reaches the generated document.
	 *
	 * @return array<string, string>
	 */
	protected function buildRefSpec(string $ref): array
	{
		$spec = ['$ref' => '#/components/schemas/' . $this->resolveRefName($ref)];

		if ($this->looksLikeClassName($ref)) {
			$spec['_class'] = ltrim($ref, '\\');
		}

		return $spec;
	}

}
