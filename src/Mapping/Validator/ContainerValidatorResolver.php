<?php declare(strict_types = 1);

namespace Sabservis\Api\Mapping\Validator;

use Nette\DI\Container;
use Pocta\DataMapper\Validation\ConstraintValidatorInterface;
use Pocta\DataMapper\Validation\ValidatorResolverInterface;

final class ContainerValidatorResolver implements ValidatorResolverInterface
{

	public function __construct(private readonly Container $container)
	{
	}

	/**
	 * @param class-string<ConstraintValidatorInterface> $validatorClass
	 */
	public function resolve(string $validatorClass): ConstraintValidatorInterface
	{
		return $this->container->getByType($validatorClass);
	}

}
