<?php declare(strict_types = 1);

if ($argc < 2) {
	fwrite(STDERR, "Usage: php tools/coverage-summary.php <clover-file> [minimum-percent]\n");
	exit(2);
}

$cloverFile = $argv[1];
$minimumPercent = $argc >= 3 ? (float) $argv[2] : null;

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

$coveragePercent = $coveredStatements / $statements * 100;

printf(
	"Line coverage: %.2f%% (%d/%d statements)\n",
	$coveragePercent,
	$coveredStatements,
	$statements,
);

$thresholdStatus = 'n/a';

if ($minimumPercent !== null) {
	$thresholdMet = $coveragePercent >= $minimumPercent;
	$thresholdStatus = $thresholdMet ? 'met' : 'not met';

	printf("Threshold: >= %.2f%% (%s)\n", $minimumPercent, $thresholdStatus);
}

$summaryPath = getenv('GITHUB_STEP_SUMMARY');

if (is_string($summaryPath) && $summaryPath !== '') {
	$summary = "### PHPUnit Coverage\n\n";
	$summary .= "| Metric | Value |\n";
	$summary .= "| --- | --- |\n";
	$summary .= sprintf(
		"| Line coverage | %.2f%% (%d/%d statements) |\n",
		$coveragePercent,
		$coveredStatements,
		$statements,
	);

	if ($minimumPercent !== null) {
		$summary .= sprintf(
			"| Threshold | >= %.2f%% (%s) |\n",
			$minimumPercent,
			$thresholdStatus,
		);
	}

	file_put_contents($summaryPath, $summary, FILE_APPEND);
}
