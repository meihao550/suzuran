<?php

namespace Suzuran\Feature;

interface LogMelInterface
{
    /**
     * @param float[] $waveform mono 16 kHz waveform, normalized to [-1, 1]
     * @return float[][] log-mel matrix shaped [nMels][nFrames]
     */
    public function compute(array $waveform): array;
}
