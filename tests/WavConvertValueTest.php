<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suzuran\WavConvertValue;

class WavConvertValueTest extends TestCase
{
    public function testReadsScaleWav(): void
    {
        $wav = new WavConvertValue(__DIR__ . '/../scale.wav');

        // data chunk size = 0x0006ff90 = 458640 bytes / 2 bytes per sample = 229320 samples
        $this->assertCount(229320, $wav->samples());
        $this->assertSame(44100, $wav->sampleRate());
    }

    public function testFirstSamplesMatchRawBytes(): void
    {
        $wav = new WavConvertValue(__DIR__ . '/../scale.wav');
        // xxd -s 44 -l 20 scale.wav → 0000 0200 0b00 1800 2c00 4400 6300 8600 af00 dc00
        $expected = [0, 2, 11, 24, 44, 68, 99, 134, 175, 220];
        $this->assertSame($expected, array_slice($wav->samples(), 0, 10));
    }

    public function testRejectsNonRiff(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notriff') . '.wav';
        file_put_contents($path, "NOPE\x00\x00\x00\x00WAVE");
        try {
            $this->expectException(RuntimeException::class);
            new WavConvertValue($path);
        } finally {
            @unlink($path);
        }
    }
}
