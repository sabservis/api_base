<?php declare(strict_types = 1);

namespace Tests\Cases\Adjuster;

require_once __DIR__ . '/../../bootstrap.php';

use QaData\Psr7\Psr7ResponseFactory;
use Sabservis\Api\Adjuster\FileResponseAdjuster;
use Sabservis\Api\Http\ApiResponse;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

Environment::setupFunctions();

final class FileResponseAdjusterTest extends TestCase
{

	public function testResponse(): void
	{
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
			$response->getHeaders(),
		);
	}

}

$test = new FileResponseAdjusterTest();
$test->run();

