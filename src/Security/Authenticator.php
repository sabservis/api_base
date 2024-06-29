<?php declare(strict_types = 1);

namespace Sabservis\Api\Security;

use Psr\Http\Message\ServerRequestInterface;

interface Authenticator
{

	public function authenticate(ServerRequestInterface $request): mixed;

}
