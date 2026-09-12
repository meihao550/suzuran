<?php

namespace Suzuran;

class CosineScorer
{
    public function score(array $a, array $b): float
    {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        foreach ($a as $key => $value) {
            $dot += $value * $b[$key];
            $na  += $value * $value;
            $nb  += $b[$key] * $b[$key];
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}