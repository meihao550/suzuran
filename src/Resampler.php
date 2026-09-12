<?php

namespace Suzuran;

use RuntimeException;

class Resampler
{
    /**
     * @param int[] $samples 16-bit signed PCM
     * @return float[] waveform normalized to [-1, 1] at $toRate
     */
    public function resample(array $samples, int $fromRate, int $toRate): array
    {
        if ($fromRate <= 0 || $toRate <= 0) {
            throw new RuntimeException('sample rate must be positive');
        }

        $inCount = count($samples);
        if ($inCount === 0) {
            return [];
        }

        if ($fromRate === $toRate) {
            $out = [];
            foreach ($samples as $s) {
                $out[] = $s / 32768.0;
            }
            return $out;
        }

        $ratio = $fromRate / $toRate;
        $outCount = (int) floor($inCount / $ratio);
        $out = array_fill(0, $outCount, 0.0);
        for ($i = 0; $i < $outCount; $i++) {
            $srcPos = $i * $ratio;
            $idx = (int) floor($srcPos);
            $frac = $srcPos - $idx;
            $a = $samples[$idx] / 32768.0;
            $b = ($idx + 1 < $inCount ? $samples[$idx + 1] : $samples[$idx]) / 32768.0;
            $out[$i] = $a + ($b - $a) * $frac;
        }
        return $out;
    }
}
