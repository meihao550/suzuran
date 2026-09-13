<?php
/**
 * End-to-end walkthrough of the parts of the pipeline that don't need the
 * ReDimNet2.onnx model. Run: `php bin/demo.php`.
 */

require __DIR__ . '/../vendor/autoload.php';

use Suzuran\CosineScorer;
use Suzuran\Feature\PhpLogMel;
use Suzuran\Resampler;
use Suzuran\Verdict;
use Suzuran\WavConvertValue;

$wavPath = __DIR__ . '/../scale.wav';

echo "[1] Decode WAV\n";
$t0 = microtime(true);
$wav = new WavConvertValue($wavPath);
$samples = $wav->samples();
printf("    file       : %s\n", $wavPath);
printf("    sampleRate : %d Hz\n", $wav->sampleRate());
printf("    samples    : %d (%.2f sec)\n", count($samples), count($samples) / $wav->sampleRate());
printf("    first 5    : %s\n", implode(', ', array_slice($samples, 0, 5)));
printf("    time       : %.3f sec\n\n", microtime(true) - $t0);

echo "[2] Resample to 16 kHz\n";
$t0 = microtime(true);
$resampler = new Resampler();
$waveform = $resampler->resample($samples, $wav->sampleRate(), 16000);
printf("    samples    : %d (%.2f sec at 16 kHz)\n", count($waveform), count($waveform) / 16000);
printf("    range      : %.3f .. %.3f\n", min($waveform), max($waveform));
printf("    time       : %.3f sec\n\n", microtime(true) - $t0);

echo "[3] Compute log-mel (PhpLogMel)\n";
$t0 = microtime(true);
$mel = (new PhpLogMel())->compute($waveform);
printf("    shape      : [%d mels x %d frames]\n", count($mel), count($mel[0]));
$slice = array_slice($mel[40], 0, 5);
printf("    mel[40][0..5] : %s\n", implode(', ', array_map(fn ($v) => number_format($v, 3), $slice)));
printf("    time       : %.3f sec\n\n", microtime(true) - $t0);

echo "[4] Cosine scorer sanity check\n";
$scorer = new CosineScorer();
printf("    identical  : %.6f (should be 1.0)\n", $scorer->score([1, 2, 3], [1, 2, 3]));
printf("    orthogonal : %.6f (should be 0.0)\n", $scorer->score([1, 0], [0, 1]));
printf("    opposite   : %.6f (should be -1.0)\n\n", $scorer->score([1, 1], [-1, -1]));

echo "[5] Verdict\n";
printf("    score 0.85 threshold 0.7 -> %s\n", (new Verdict(0.85, 0.7))->isMatch() ? 'MATCH' : 'no match');
printf("    score 0.50 threshold 0.7 -> %s\n\n", (new Verdict(0.50, 0.7))->isMatch() ? 'MATCH' : 'no match');

echo "[6] Missing pieces for full isMatch()\n";
$model = __DIR__ . '/../models/redimnet2.onnx';
$lib   = '/opt/homebrew/lib/libonnxruntime.dylib';
printf("    libonnxruntime : %s\n", file_exists($lib) ? "OK ($lib)" : 'NOT FOUND');
printf("    ReDimNet2.onnx : %s\n", file_exists($model) ? "OK ($model)" : 'MISSING (run scripts/export_redimnet.py)');
