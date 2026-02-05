<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Contact implements OpenApiAttributeInterface
{

	public function __construct(
		public string|null $name = null,
		public string|null $url = null,
		public string|null $email = null,
	)
	{
	}

	/**
	 * @return array<string, string>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = [];

		if ($this->name !== null) {
			$spec['name'] = $this->name;
		}

		if ($this->url !== null) {
			$spec['url'] = $this->url;
		}

		if ($this->email !== null) {
			$spec['email'] = $this->email;
		}

		return $spec;
	}

}
