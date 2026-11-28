<?php

/**
 * Line coverage of the Domain folders from a Clover report (CLAUDE.md §14 Maintainability: at least 70%).
 *
 *   php tests/coverage-summary.php build/coverage.xml [--min=70]
 *
 * Prints the total and each Domain folder, adds them to the GitHub Actions job summary and a
 * notice annotation when run in CI, and exits 1 below --min.
 */
$file = $argv[1] ?? 'build/coverage.xml';
$min = null;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--min=')) {
        $min = (float) substr($arg, 6);
    }
}
if (! is_file($file)) {
    fwrite(STDERR, "No coverage report at {$file}.\n");
    exit(2);
}

$xml = simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Cannot read {$file}.\n");
    exit(2);
}

$folders = [];
foreach ($xml->xpath('//file') ?: [] as $f) {
    $path = str_replace('\\', '/', (string) $f['name']);
    if (! preg_match('#/app/Domain/([^/]+)/#', $path, $m)) {
        continue;
    }
    $metrics = $f->metrics;
    $folders[$m[1]] ??= [0, 0];
    $folders[$m[1]][0] += (int) $metrics['coveredstatements'];
    $folders[$m[1]][1] += (int) $metrics['statements'];
}
ksort($folders);

$covered = array_sum(array_column($folders, 0));
$total = array_sum(array_column($folders, 1));
$pct = fn (int $c, int $t) => $t > 0 ? $c / $t * 100 : 0.0;

$lines = ['| Domain folder | Lines covered | Coverage |', '|---|---:|---:|'];
foreach ($folders as $name => [$c, $t]) {
    $lines[] = sprintf('| %s | %d / %d | %.1f%% |', $name, $c, $t, $pct($c, $t));
}
$summary = sprintf('Domain coverage: %.1f%% (%d of %d lines)', $pct($covered, $total), $covered, $total);
$lines[] = sprintf('| **All Domain folders** | **%d / %d** | **%.1f%%** |', $covered, $total, $pct($covered, $total));

echo $summary.PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL;

if ($step = getenv('GITHUB_STEP_SUMMARY')) {
    file_put_contents($step, "### {$summary}\n\n".implode("\n", $lines)."\n", FILE_APPEND);
}
if (getenv('GITHUB_ACTIONS')) {
    echo "::notice title=Domain coverage::{$summary}".PHP_EOL;
}

exit($min !== null && $pct($covered, $total) < $min ? 1 : 0);
