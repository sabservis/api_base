<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\PolymorphicBody;

use Sabservis\Api\Attribute\OpenApi\Property;

class NoteRequest
{

	#[Property(example: 'Poznámka')]
	public string $text;

}
