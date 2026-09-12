<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\TestCase;
use Suzuran\Feature\PhpLogMel;

class PhpLogMelTest extends TestCase
{
    public function testShapeIs80xExpectedFrames(): void
    {
        $sampleRate = 16000;
        $duration = 1.0;
        $n = (int) ($sampleRate * $duration);
        $waveform = [];
        for ($i = 0; $i < $n; $i++) {
            $waveform[] = 0.5 * sin(2 * M_PI * 1000 * $i / $sampleRate);
        }
        $mel = (new PhpLogMel())->compute($waveform);

        $this->assertCount(80, $mel);
        $expectedFrames = 1 + (int) floor(($n - 400) / 160);
        $this->assertCount($expectedFrames, $mel[0]);
    }

    public function testSineHasEnergyNearExpectedMelBin(): void
    {
        $sampleRate = 16000;
        $freq = 1000.0;
        $n = 8000; // 0.5s
        $waveform = [];
        for ($i = 0; $i < $n; $i++) {
            $waveform[] = 0.5 * sin(2 * M_PI * $freq * $i / $sampleRate);
        }
        $mel = (new PhpLogMel())->compute($waveform);

        $frameIndex = (int) (count($mel[0]) / 2);
        $energies = [];
        for ($m = 0; $m < 80; $m++) {
            $energies[$m] = $mel[$m][$frameIndex];
        }
        $peakBin = array_keys($energies, max($energies))[0];
        // 1 kHz on a 80-mel bank spanning 0..8 kHz should peak roughly in the middle band.
        $this->assertGreaterThan(20, $peakBin);
        $this->assertLessThan(60, $peakBin);
    }
}
