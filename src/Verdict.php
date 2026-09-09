<?php
/* 
閾値から音声が本人かどうかを正解ラベルを出す
*/
namespace Suzuran;

class Verdict
{
    public function __construct(
        private float $score,
        private float $threshold,
    ) {
        // nothing to do
    }

    public function score(): float
    {
        return $this->score;
    }

    public function isMatch(): bool
    {
        return $this->score >= $this->threshold;
    }

}