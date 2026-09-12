<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\TestCase;
use Suzuran\Feature\PhpLogMel;
use Suzuran\Feature\RustLogMel;

class RustLogMelTest extends TestCase
{
    public function testMatchesPhpImplementationOnSine(): void
    {
        $binary = __DIR__ . '/../bin/logmel-rs';
        if (!is_executable($binary)) {
            $this->markTestSkipped("bin/logmel-rs is not built (run bin/setup.sh)");
        }

        $waveform = [];
        for ($i = 0; $i < 8000; $i++) {
            $waveform[] = 0.5 * sin(2 * M_PI * 1000 * $i / 16000);
        }
        $php = (new PhpLogMel())->compute($waveform);
        $rust = (new RustLogMel($binary))->compute($waveform);

        $this->assertCount(count($php), $rust);
        $this->assertCount(count($php[0]), $rust[0]);

        $mid = (int) (count($php[0]) / 2);
        for ($m = 0; $m < 80; $m += 10) {
            $this->assertEqualsWithDelta($php[$m][$mid], $rust[$m][$mid], 0.5, "mel[$m][$mid]");
        }
    }
}
