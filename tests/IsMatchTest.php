<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Suzuran\Suzuran;

class IsMatchTest extends TestCase
{
    private const MODEL_PATH = __DIR__ . '/../models/redimnet2.onnx';

    private Suzuran $suzuran;

    protected function setUp(): void
    {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('ext-ffi not loaded');
        }
        if (!file_exists(self::MODEL_PATH)) {
            $this->markTestSkipped('models/redimnet2.onnx not present (run bin/setup.sh)');
        }
        $this->suzuran = new Suzuran();
    }

    #[DataProvider('casesProvider')]
    public function testIsMatch(string $wav1, string $wav2, float $threshold, bool $expected): void
    {
        $this->assertSame($expected, $this->suzuran->isMatch($wav1, $wav2, $threshold));
    }

    /** @return array<string, array{string, string, float, bool}> */
    public static function casesProvider(): array
    {
        return [
            'self-match' => [__DIR__ . '/../scale.wav', __DIR__ . '/../scale.wav', 0.7, true],
            // Add your own pairs here:
            // 'diff speaker' => [__DIR__ . '/fixtures/A.wav', __DIR__ . '/fixtures/B.wav', 0.7, false],
        ];
    }
}
