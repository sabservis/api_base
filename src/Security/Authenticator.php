<?php declare(strict_types = 1);

namespace Sabservis\Api\Security;

use Sabservis\Api\Http\ApiRequest;

interface Authenticator
{

	public function authenticate(ApiRequest $request): mixed;

}
