<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Tag implements OpenApiAttributeInterface
{

	public function __construct(
		public string $name,
		public string|null $description = null,
		public mixed $value = null,
		public bool $hidden = false,
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getValue(): mixed
	{
		return $this->value;
	}

	public function isHidden(): bool
	{
		return $this->hidden;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$spec = ['name' => $this->name];

		if ($this->description !== null) {
			$spec['description'] = $this->description;
		}

		return $spec;
	}

}
