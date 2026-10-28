<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\UI\Controller\Controller;
use Tests\Unit\OpenApi\Fixtures\Collision\Order\ItemDto;

final class InlineOnlyController implements Controller
{

	// ItemDto is referenced nowhere else

	#[Post(path: '/inline')]
	#[RequestBody(content: new JsonContent(oneOf: [new Schema(ref: ItemDto::class)]))]
	public function inline(): ApiResponse
	{
		return new ApiResponse();
	}

}
