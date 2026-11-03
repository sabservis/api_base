<?php declare(strict_types = 1);

namespace Sabservis\Api\Middleware;

use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use function base64_decode;
use function count;
use function explode;
use function hash_equals;
use function password_verify;
use function preg_match;

#[MiddlewarePriority(460)]
class BasicAuthMiddleware implements Middleware
{

	/** @var array<string, array{unsecured: bool, password: string}> */
	private array $users = [];

	public function __construct(private readonly string $title = 'Restrict zone')
	{
	}

	public function addUser(string $user, string $password, bool $unsecured = false): self
	{
		$this->users[$user] = [
			'password' => $password,
			'unsecured' => $unsecured,
		];

		return $this;
	}

	protected function auth(string $user, string $password): bool
	{
		// Dummy hash for constant-time comparison when user doesn't exist
		// This prevents timing attacks that could reveal valid usernames
		$dummyHash = '$2y$10$dummyHashForTimingAttackPreventionXXXXXXXXXXXXXXX';

		$userData = $this->users[$user] ?? [
			'password' => $dummyHash,
			'unsecured' => false,
		];
		$userExists = isset($this->users[$user]);

		// Always perform password verification to ensure constant-time execution
		$passwordValid = $userData['unsecured'] === true
			? hash_equals($userData['password'], $password)
			: password_verify($password, $userData['password']);

		// Both conditions must be true
		return $userExists && $passwordValid;
	}

	/**
	 * @return array{username: string, password: string}|null
	 */
	protected function parseAuthorizationHeader(string $header): array|null
	{
		// RFC 7235: scheme name is case-insensitive and must be a whole token ("Basicx" is not Basic)
		if (preg_match('/^Basic\s+(\S+)\s*\z/i', $header, $matches) !== 1) {
			return null;
		}

		$header = explode(':', (string) base64_decode($matches[1], true), 2);

		if (count($header) !== 2) {
			return null;
		}

		return [
			'password' => $header[1],
			'username' => $header[0],
		];
	}

	public function __invoke(
		ApiRequest $request,
		ApiResponse $response,
		callable $next,
	): ApiResponse
	{
		$authorization = $this->parseAuthorizationHeader($request->getHeader('Authorization') ?? '');

		if ($authorization !== null && $this->auth($authorization['username'], $authorization['password'])) {
			return $next(
				$request->withAttribute('username', $authorization['username']),
				$response,
			);
		}

		return $response
			->withStatus(401)
			->withHeader('WWW-Authenticate', 'Basic realm="' . $this->title . '"');
	}

}
