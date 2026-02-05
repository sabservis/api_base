<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Server implements OpenApiAttributeInterface
{

	public function __construct(
		public string $url,
		public string|null $description = null,
	)
	{
	}

	/**
	 * @return array<string, string>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = ['url' => $this->url];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		return $spec;
	}

}
