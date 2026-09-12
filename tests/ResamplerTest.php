<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\TestCase;
use Suzuran\Resampler;

class ResamplerTest extends TestCase
{
    public function testPassThroughNormalizesTo16Bit(): void
    {
        $r = new Resampler();
        $out = $r->resample([0, 16384, -16384, 32767], 16000, 16000);
        $this->assertCount(4, $out);
        $this->assertEqualsWithDelta(0.0, $out[0], 1e-9);
        $this->assertEqualsWithDelta(0.5, $out[1], 1e-3);
        $this->assertEqualsWithDelta(-0.5, $out[2], 1e-3);
        $this->assertLessThan(1.0, $out[3]);
    }

    public function testDownsampleSineKeepsFrequencyBucket(): void
    {
        $r = new Resampler();
        $freq = 1000.0;
        $fromRate = 44100;
        $duration = 0.1;
        $n = (int) ($fromRate * $duration);
        $samples = [];
        for ($i = 0; $i < $n; $i++) {
            $samples[] = (int) round(30000 * sin(2 * M_PI * $freq * $i / $fromRate));
        }
        $out = $r->resample($samples, $fromRate, 16000);
        $this->assertGreaterThan((int) (16000 * $duration) - 5, count($out));
        $this->assertLessThan((int) (16000 * $duration) + 5, count($out));
        $max = max(array_map('abs', $out));
        $this->assertGreaterThan(0.5, $max);
    }
}
