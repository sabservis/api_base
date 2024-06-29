<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Normalizer\Types;

use Sabservis\Api\Mapping\Normalizer\NormalizerProvider;

abstract class AbstractTypeNormalizer implements TypeNormalizer
{

	protected NormalizerProvider $provider;

	public function setProvider(NormalizerProvider $provider): void
	{
		$this->provider = $provider;
	}

}
