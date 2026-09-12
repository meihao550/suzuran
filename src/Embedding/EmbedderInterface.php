<?php

namespace Suzuran\Embedding;

interface EmbedderInterface
{
    /**
     * @param float[][] $mel log-mel matrix, shape [nMels][nFrames]
     * @return float[] 192-dim speaker embedding
     */
    public function embed(array $mel): array;
}
