<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Define security requirements for controller or method.
 *
 * When applied to a controller, sets default security for all endpoints.
 * When applied to a method, overrides controller-level security.
 *
 * Use empty array [] to disable security (public endpoint).
 *
 * Usage:
 *   // Controller-level (applies to all methods)
 *   #[Security([['Bearer' => []]])]
 *   class UserController { }
 *
 *   // Method-level (overrides controller)
 *   #[Security([])]  // Public endpoint
 *   public function login() { }
 *
 *   // Multiple security options (OR relationship)
 *   #[Security([['Bearer' => []], ['ApiKey' => []]])]
 *   public function getData() { }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Security implements OpenApiAttributeInterface
{

	/**
	 * @param array<array<string, array<string>>> $security Security requirements.
	 *        Each element is an OR option, keys are security scheme names,
	 *        values are required scopes (empty array for no scopes).
	 *        Empty array [] means no security required (public endpoint).
	 */
	public function __construct(public array $security)
	{
	}

	/**
	 * @return array<array<string, array<string>>>
	 */
	public function getSecurity(): array
	{
		return $this->security;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		return ['security' => $this->security];
	}

}
