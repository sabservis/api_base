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
				'Cache-Control' => ['must-revalidate, post-check=0, pre-check=0'],
				'Content-Description' => ['File Transfer'],
				'Content-Disposition' => ['attachment; filename="filename"; filename*=utf-8\'\'filename'],
				'Content-Length' => ['0'],
				'Content-Transfer-Encoding' => ['binary'],
				'Content-Type' => ['application/octet-stream'],
				'Expires' => ['0'],
				'Pragma' => ['public'],
			],
			$response->getHeaders(),
		);
	}

}

$test = new FileResponseAdjusterTest();
$test->run();
