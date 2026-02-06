<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema;

use Sabservis\Api\Security\Authorizer;

final class EndpointAuthorization
{

	/**
	 * @param class-string<Authorizer> $authorizer
	 */
	public function __construct(
		private string $activity,
		private string $authorizer,
	)
	{
	}

	public function getActivity(): string
	{
		return $this->activity;
	}

	/**
	 * @return class-string<Authorizer>
	 */
	public function getAuthorizer(): string
	{
		return $this->authorizer;
	}

}
