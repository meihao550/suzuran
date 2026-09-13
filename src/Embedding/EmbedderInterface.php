<?php

namespace Suzuran\Embedding;

interface EmbedderInterface
{
    /**
     * @param float[] $waveform raw 16 kHz mono waveform in [-1, 1]
     * @return float[] 192-dim speaker embedding
     */
    public function embed(array $waveform): array;
}
