<?php

namespace Suzuran\Feature;

use RuntimeException;

class PhpLogMel implements LogMelInterface
{
    private const SAMPLE_RATE = 16000;
    private const N_FFT = 512;
    private const WIN_LENGTH = 400;
    private const HOP_LENGTH = 160;
    private const N_MELS = 80;
    private const F_MIN = 0.0;
    private const F_MAX = 8000.0;
    private const LOG_FLOOR = 1e-10;

    /** @var float[] */
    private array $window;
    /** @var float[][] shape [nMels][nFftBins] */
    private array $melFilters;
    /** @var array{re: float[], im: float[]} precomputed twiddles */
    private array $twiddle;

    public function __construct()
    {
        if ((self::N_FFT & (self::N_FFT - 1)) !== 0) {
            throw new RuntimeException('N_FFT must be a power of two');
        }
        $this->window = $this->hannWindow(self::WIN_LENGTH);
        $this->melFilters = $this->buildMelFilters();
        $this->twiddle = $this->buildTwiddle(self::N_FFT);
    }

    public function compute(array $waveform): array
    {
        $n = count($waveform);
        $nFrames = $n < self::WIN_LENGTH ? 0 : 1 + (int) floor(($n - self::WIN_LENGTH) / self::HOP_LENGTH);
        $nBins = (int) (self::N_FFT / 2) + 1;

        $mel = array_fill(0, self::N_MELS, array_fill(0, $nFrames, 0.0));

        $re = array_fill(0, self::N_FFT, 0.0);
        $im = array_fill(0, self::N_FFT, 0.0);

        for ($t = 0; $t < $nFrames; $t++) {
            $start = $t * self::HOP_LENGTH;
            for ($i = 0; $i < self::N_FFT; $i++) {
                $re[$i] = 0.0;
                $im[$i] = 0.0;
            }
            for ($i = 0; $i < self::WIN_LENGTH; $i++) {
                $re[$i] = $waveform[$start + $i] * $this->window[$i];
            }
            $this->fftInPlace($re, $im);

            $power = array_fill(0, $nBins, 0.0);
            for ($k = 0; $k < $nBins; $k++) {
                $power[$k] = $re[$k] * $re[$k] + $im[$k] * $im[$k];
            }

            for ($m = 0; $m < self::N_MELS; $m++) {
                $sum = 0.0;
                $filter = $this->melFilters[$m];
                for ($k = 0; $k < $nBins; $k++) {
                    $sum += $filter[$k] * $power[$k];
                }
                $mel[$m][$t] = log(max($sum, self::LOG_FLOOR));
            }
        }

        return $mel;
    }

    /** @return float[] */
    private function hannWindow(int $len): array
    {
        $w = array_fill(0, $len, 0.0);
        for ($i = 0; $i < $len; $i++) {
            $w[$i] = 0.5 * (1.0 - cos(2.0 * M_PI * $i / ($len - 1)));
        }
        return $w;
    }

    /** @return float[][] */
    private function buildMelFilters(): array
    {
        $nBins = (int) (self::N_FFT / 2) + 1;
        $melMin = $this->hzToMel(self::F_MIN);
        $melMax = $this->hzToMel(self::F_MAX);

        $melPoints = [];
        for ($i = 0; $i < self::N_MELS + 2; $i++) {
            $melPoints[$i] = $melMin + ($melMax - $melMin) * $i / (self::N_MELS + 1);
        }
        $hzPoints = array_map(fn ($m) => $this->melToHz($m), $melPoints);
        $binPoints = array_map(
            fn ($hz) => (int) floor(($hz * self::N_FFT) / self::SAMPLE_RATE),
            $hzPoints
        );

        $filters = array_fill(0, self::N_MELS, array_fill(0, $nBins, 0.0));
        for ($m = 1; $m <= self::N_MELS; $m++) {
            $left = $binPoints[$m - 1];
            $center = $binPoints[$m];
            $right = $binPoints[$m + 1];
            for ($k = $left; $k < $center; $k++) {
                if ($center === $left || $k >= $nBins) {
                    continue;
                }
                $filters[$m - 1][$k] = ($k - $left) / ($center - $left);
            }
            for ($k = $center; $k < $right; $k++) {
                if ($right === $center || $k >= $nBins) {
                    continue;
                }
                $filters[$m - 1][$k] = ($right - $k) / ($right - $center);
            }
        }
        return $filters;
    }

    private function hzToMel(float $hz): float
    {
        return 2595.0 * log10(1.0 + $hz / 700.0);
    }

    private function melToHz(float $mel): float
    {
        return 700.0 * (10 ** ($mel / 2595.0) - 1.0);
    }

    /** @return array{re: float[], im: float[]} */
    private function buildTwiddle(int $n): array
    {
        $re = array_fill(0, (int) ($n / 2), 0.0);
        $im = array_fill(0, (int) ($n / 2), 0.0);
        for ($k = 0; $k < $n / 2; $k++) {
            $angle = -2.0 * M_PI * $k / $n;
            $re[$k] = cos($angle);
            $im[$k] = sin($angle);
        }
        return ['re' => $re, 'im' => $im];
    }

    /**
     * In-place radix-2 iterative FFT (Cooley-Tukey).
     * @param float[] $re
     * @param float[] $im
     */
    private function fftInPlace(array &$re, array &$im): void
    {
        $n = self::N_FFT;
        // bit reversal
        $j = 0;
        for ($i = 1; $i < $n; $i++) {
            $bit = $n >> 1;
            while ($j & $bit) {
                $j ^= $bit;
                $bit >>= 1;
            }
            $j |= $bit;
            if ($i < $j) {
                [$re[$i], $re[$j]] = [$re[$j], $re[$i]];
                [$im[$i], $im[$j]] = [$im[$j], $im[$i]];
            }
        }

        $twRe = $this->twiddle['re'];
        $twIm = $this->twiddle['im'];
        for ($size = 2; $size <= $n; $size <<= 1) {
            $half = $size >> 1;
            $step = (int) ($n / $size);
            for ($start = 0; $start < $n; $start += $size) {
                $twIdx = 0;
                for ($k = $start; $k < $start + $half; $k++) {
                    $wr = $twRe[$twIdx];
                    $wi = $twIm[$twIdx];
                    $tRe = $wr * $re[$k + $half] - $wi * $im[$k + $half];
                    $tIm = $wr * $im[$k + $half] + $wi * $re[$k + $half];
                    $re[$k + $half] = $re[$k] - $tRe;
                    $im[$k + $half] = $im[$k] - $tIm;
                    $re[$k] += $tRe;
                    $im[$k] += $tIm;
                    $twIdx += $step;
                }
            }
        }
    }
}
