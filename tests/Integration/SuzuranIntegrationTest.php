<?php

namespace Suzuran\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Suzuran\Suzuran;

class SuzuranIntegrationTest extends TestCase
{
    public function testSelfIsMatch(): void
    {
        $model = __DIR__ . '/../../models/redimnet2.onnx';
        if (!file_exists($model)) {
            $this->markTestSkipped("models/redimnet2.onnx not present (run bin/setup.sh)");
        }
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('ext-ffi not loaded');
        }

        $suzuran = new Suzuran();
        $wav = __DIR__ . '/../../scale.wav';
        $this->assertTrue($suzuran->isMatch($wav, $wav, 0.7));
    }
}
