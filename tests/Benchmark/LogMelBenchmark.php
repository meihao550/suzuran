<?php

require __DIR__ . '/../../vendor/autoload.php';

use Suzuran\Feature\PhpLogMel;
use Suzuran\Feature\RustLogMel;

$sampleRate = 16000;
$duration = 10.0;
$n = (int) ($sampleRate * $duration);
$waveform = [];
for ($i = 0; $i < $n; $i++) {
    $waveform[] = 0.3 * sin(2 * M_PI * 440 * $i / $sampleRate);
}

$phpStart = microtime(true);
$mel = (new PhpLogMel())->compute($waveform);
$phpElapsed = microtime(true) - $phpStart;
printf("PhpLogMel  : %.3fs (mel shape %dx%d)\n", $phpElapsed, count($mel), count($mel[0]));

$binary = __DIR__ . '/../../bin/logmel-rs';
if (!is_executable($binary)) {
    echo "RustLogMel : skipped (bin/logmel-rs not built)\n";
    return;
}

$rustStart = microtime(true);
$mel = (new RustLogMel($binary))->compute($waveform);
$rustElapsed = microtime(true) - $rustStart;
printf("RustLogMel : %.3fs (mel shape %dx%d)\n", $rustElapsed, count($mel), count($mel[0]));
printf("Speedup    : %.1fx\n", $phpElapsed / max($rustElapsed, 1e-9));
