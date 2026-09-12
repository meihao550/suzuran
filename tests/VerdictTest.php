<?php

namespace Suzuran\Tests;

use PHPUnit\Framework\TestCase;
use Suzuran\Verdict;

class VerdictTest extends TestCase
{
    public function testMatchesAboveThreshold(): void
    {
        $this->assertTrue((new Verdict(0.8, 0.7))->isMatch());
    }

    public function testMissesBelowThreshold(): void
    {
        $this->assertFalse((new Verdict(0.5, 0.7))->isMatch());
    }

    public function testEqualToThresholdCountsAsMatch(): void
    {
        $this->assertTrue((new Verdict(0.7, 0.7))->isMatch());
    }
}
