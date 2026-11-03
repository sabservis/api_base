<?php declare(strict_types = 1);

namespace Tests\Unit\OpenApi\Fixtures\PolymorphicBody;

use Sabservis\Api\Attribute\OpenApi\Examples;
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\UI\Controller\Controller;

final class PortfolioController implements Controller
{

	/**
	 * @return array<mixed>
	 */
	#[Post(path: '/portfolios')]
	#[RequestBody(ref: PortfolioRequest::class, required: true)]
	public function create(): array
	{
		return [];
	}

	/**
	 * @return array<mixed>
	 */
	#[Post(path: '/portfolios/json-content')]
	#[RequestBody(content: new JsonContent(ref: PortfolioRequest::class))]
	public function createViaJsonContent(): array
	{
		return [];
	}

	/**
	 * @return array<mixed>
	 */
	#[Post(path: '/portfolios/manual-examples')]
	#[RequestBody(content: new JsonContent(
		ref: PortfolioRequest::class,
		examples: [new Examples(example: 'custom', summary: 'Ruční', value: ['type' => 'Savings'])],
	))]
	public function createWithManualExamples(): array
	{
		return [];
	}

	/**
	 * @return array<mixed>
	 */
	#[Post(path: '/portfolios/manual-example')]
	#[RequestBody(content: new JsonContent(ref: PortfolioRequest::class, example: ['type' => 'Savings']))]
	public function createWithManualExample(): array
	{
		return [];
	}

	/**
	 * @return array<mixed>
	 */
	#[Post(path: '/notes')]
	public function createNote(NoteRequest $input): array
	{
		return [];
	}

}
