<?php declare(strict_types = 1);

require_once __DIR__ . '/../../bootstrap.php';

use Sabservis\Api\Adjuster\FileResponseAdjuster;
use Sabservis\Api\Http\ApiResponse;
use QaData\Psr7\Psr7ResponseFactory;
use Tester\Assert;

Tester\Environment::setupFunctions();

test('', function (): void {
	$response = new ApiResponse(Psr7ResponseFactory::fromGlobal());
	$response = FileResponseAdjuster::adjust($response, $response->getBody(), 'filename');

	Assert::same(
		[
			'Content-Type' => ['application/octet-stream'],
			'Content-Description' => ['File Transfer'],
			'Content-Transfer-Encoding' => ['binary'],
			'Content-Disposition' => ['attachment; filename="filename"; filename*=utf-8\'\'filename'],
			'Expires' => ['0'],
			'Cache-Control' => ['must-revalidate, post-check=0, pre-check=0'],
			'Pragma' => ['public'],
			'Content-Length' => ['0'],
		],
		$response->getHeaders()
	);
});
