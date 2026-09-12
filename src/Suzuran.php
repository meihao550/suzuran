<?php

namespace Suzuran;

use Suzuran\Embedding\EmbedderInterface;
use Suzuran\Embedding\ReDimNetEmbedder;
use Suzuran\Feature\LogMelInterface;
use Suzuran\Feature\PhpLogMel;

class Suzuran
{
    private const TARGET_RATE = 16000;

    public function __construct(
        private ?EmbedderInterface $embedder = null,
        private ?LogMelInterface $logMel = null,
        private Resampler $resampler = new Resampler(),
        private CosineScorer $scorer = new CosineScorer(),
    ) {
        $this->logMel ??= new PhpLogMel();
        $this->embedder ??= new ReDimNetEmbedder(__DIR__ . '/../models/redimnet2.onnx');
    }

    public function isMatch(string $wav1, string $wav2, float $threshold): bool
    {
        $score = $this->scorer->score($this->embed($wav1), $this->embed($wav2));
        return (new Verdict($score, $threshold))->isMatch();
    }

    /** @return float[] */
    public function embed(string $wavPath): array
    {
        $wav = new WavConvertValue($wavPath);
        $waveform = $this->resampler->resample($wav->samples(), $wav->sampleRate(), self::TARGET_RATE);
        $mel = $this->logMel->compute($waveform);
        return $this->embedder->embed($mel);
    }
}
