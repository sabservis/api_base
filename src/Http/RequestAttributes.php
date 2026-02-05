<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

enum RequestAttributes: string
{

	case Endpoint = 'api.endpoint';

	case Router = 'api.router';

	/**
	 * Combined parameters from path and query (for backward compatibility).
	 *
	 * @deprecated Use PathParameters or QueryParameters for explicit source distinction
	 */
	case Parameters = 'api.parameters';

	/**
	 * Parameters extracted from URL path (e.g., /users/{id} -> ['id' => '123']).
	 */
	case PathParameters = 'api.parameters.path';

	/**
	 * Parameters from query string (e.g., ?limit=10 -> ['limit' => '10']).
	 */
	case QueryParameters = 'api.parameters.query';

	case RequestEntity = 'api.request.entity';

}
