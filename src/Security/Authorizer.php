<?php declare(strict_types = 1);

namespace Sabservis\Api\Security;

use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Schema\Endpoint;

interface Authorizer
{

	public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool;

}
