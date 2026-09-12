<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\TestCase;
use Suzuran\CosineScorer;

class CosineScorerTest extends TestCase
{
    public function testIdenticalVectorsScoreOne(): void
    {
        $s = new CosineScorer();
        $this->assertEqualsWithDelta(1.0, $s->score([1, 2, 3], [1, 2, 3]), 1e-9);
    }

    public function testOrthogonalVectorsScoreZero(): void
    {
        $s = new CosineScorer();
        $this->assertEqualsWithDelta(0.0, $s->score([1, 0], [0, 1]), 1e-9);
    }

    public function testNegativeVectorsScoreMinusOne(): void
    {
        $s = new CosineScorer();
        $this->assertEqualsWithDelta(-1.0, $s->score([1, 1], [-1, -1]), 1e-9);
    }
}
