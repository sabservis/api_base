<?php declare(strict_types = 1);

namespace Sabservis\Api\Security;

use Nette\DI\Container;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Exception\ErrorMessages;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Schema\Endpoint;
use function sprintf;

final class AuthorizationChecker
{

	public function __construct(private Container $container)
	{
	}

	public function authorize(ApiRequest $request, Endpoint $endpoint): void
	{
		foreach ($endpoint->getAuthorizations() as $authorization) {
			$authorizer = $this->resolveAuthorizer($authorization->getAuthorizer());
			$allowed = $authorizer->isAllowed($request, $endpoint, $authorization->getActivity());

			if ($allowed) {
				continue;
			}

			throw new ClientErrorException(
				sprintf(ErrorMessages::FORBIDDEN_ACTIVITY, $authorization->getActivity()),
				403,
			);
		}
	}

	/**
	 * @param class-string<Authorizer> $authorizerClass
	 */
	private function resolveAuthorizer(string $authorizerClass): Authorizer
	{
		$service = $this->container->getByType($authorizerClass, false);

		if (!$service instanceof Authorizer) {
			throw new InvalidStateException(
				sprintf(
					'Authorizer service %s is not registered in DI container or does not implement %s.',
					$authorizerClass,
					Authorizer::class,
				),
			);
		}

		return $service;
	}

}
