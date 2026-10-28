<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\Collision;

use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Http\UploadedFile;

final class BasketUploadDto
{

	public Basket $basket;

	#[FileUpload(name: 'receipt')]
	public UploadedFile $receipt;

}
