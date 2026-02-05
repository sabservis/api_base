<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

interface RequestOperationAttribute
{

	public function getMethod(): string;

	public function getPath(): string|null;

}
