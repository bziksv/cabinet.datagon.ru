<?php
namespace Tests\Unit\Wordstat;

use App\Services\Wordstat\WordstatEstimator;
use App\Services\Wordstat\WordstatSettings;
use PHPUnit\Framework\TestCase;

class WordstatEstimatorTest extends TestCase
{
    public function testNoDepth(): void
    {
        $e = WordstatEstimator::estimate(12, WordstatSettings::normalize([]));
        $this->assertSame(['seeds' => 12, 'depth' => 0, 'total' => 12], $e);
    }

    public function testDepthAddsCap(): void
    {
        $e = WordstatEstimator::estimate(3, WordstatSettings::normalize(['depth' => 1, 'depth_max_seeds' => 40]));
        $this->assertSame(['seeds' => 3, 'depth' => 40, 'total' => 43], $e);
    }
}
