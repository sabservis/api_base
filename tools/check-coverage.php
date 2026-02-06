<?php declare(strict_types = 1);

if ($argc < 3) {
	fwrite(STDERR, "Usage: php tools/check-coverage.php <clover-file> <minimum-percent>\n");
	exit(2);
}

$cloverFile = $argv[1];
$minimumPercent = (float) $argv[2];

if (!is_file($cloverFile)) {
	fwrite(STDERR, "Coverage file not found: {$cloverFile}\n");
	exit(2);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($cloverFile);

if ($xml === false || !isset($xml->project->metrics)) {
	fwrite(STDERR, "Unable to read Clover metrics from: {$cloverFile}\n");
	exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);

if ($statements <= 0) {
	fwrite(STDERR, "No executable statements found in Clover report.\n");
	exit(2);
}

$coveragePercent = ($coveredStatements / $statements) * 100;

printf(
	"Line coverage: %.2f%% (%d/%d statements), required: %.2f%%\n",
	$coveragePercent,
	$coveredStatements,
	$statements,
	$minimumPercent,
);

if ($coveragePercent < $minimumPercent) {
	fwrite(STDERR, "Coverage threshold not met.\n");
	exit(1);
}

echo "Coverage threshold met.\n";
