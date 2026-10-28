<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\UI\Controller\Controller;

final class PinnedMultipartController implements Controller
{

	#[Get(path: '/cart')]
	#[Response(ref: CartDto::class)]
	public function cart(): CartDto
	{
		return new CartDto();
	}

	#[Post(path: '/basket')]
	#[RequestBody(ref: BasketUploadDto::class)]
	public function upload(BasketUploadDto $input): ApiResponse
	{
		return new ApiResponse();
	}

}
