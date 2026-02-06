<?php declare(strict_types = 1);

if ($argc < 4) {
	fwrite(STDERR, "Usage: php tools/update-test-badges.php <junit-file> <tests-badge-file> <assertions-badge-file>\n");
	exit(2);
}

$junitFile = $argv[1];
$testsBadgeFile = $argv[2];
$assertionsBadgeFile = $argv[3];

if (!is_file($junitFile)) {
	fwrite(STDERR, "JUnit file not found: {$junitFile}\n");
	exit(2);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($junitFile);

if ($xml === false) {
	fwrite(STDERR, "Unable to read JUnit XML from: {$junitFile}\n");
	exit(2);
}

$readMetric = static function (SimpleXMLElement $xml, string $name): int|null {
	if (isset($xml[$name])) {
		$value = (string) $xml[$name];

		if ($value !== '' && is_numeric($value)) {
			return (int) $value;
		}
	}

	if (isset($xml->testsuite[0][$name])) {
		$value = (string) $xml->testsuite[0][$name];

		if ($value !== '' && is_numeric($value)) {
			return (int) $value;
		}
	}

	return null;
};

$writeBadge = static function (string $targetPath, array $badge): void {
	$directory = dirname($targetPath);

	if (!is_dir($directory)) {
		mkdir($directory, 0777, true);
	}

	$json = json_encode($badge, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

	if ($json === false) {
		fwrite(STDERR, "Failed to encode badge JSON for: {$targetPath}\n");
		exit(2);
	}

	file_put_contents($targetPath, $json . "\n");
};

$tests = $readMetric($xml, 'tests');
$assertions = $readMetric($xml, 'assertions');

if ($tests === null || $assertions === null) {
	fwrite(STDERR, "Unable to read test metrics (tests/assertions) from: {$junitFile}\n");
	exit(2);
}

$writeBadge(
	$testsBadgeFile,
	[
		'schemaVersion' => 1,
		'label' => 'tests',
		'message' => (string) $tests,
		'color' => 'brightgreen',
	],
);

$writeBadge(
	$assertionsBadgeFile,
	[
		'schemaVersion' => 1,
		'label' => 'assertions',
		'message' => (string) $assertions,
		'color' => 'brightgreen',
	],
);

printf("Badges updated: tests=%d, assertions=%d\n", $tests, $assertions);
