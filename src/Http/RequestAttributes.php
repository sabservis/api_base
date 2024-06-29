<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

interface RequestAttributes
{

	public const Endpoint = 'api.endpoint';

	public const Router = 'api.router';

	public const Parameters = 'api.parameters';

	public const RequestEntity = 'api.request.entity';

	public const ResponseEntity = 'api.response.entity';

}
