<?php
namespace Tests\Unit\Wordstat;

use App\Services\Wordstat\WordstatSettings;
use PHPUnit\Framework\TestCase;

class WordstatSettingsTest extends TestCase
{
    public function testDefaultsWhenEmpty(): void
    {
        $s = WordstatSettings::normalize([]);
        $this->assertSame([213], $s['region_ids']);
        $this->assertSame('all', $s['device']);
        $this->assertSame(['left'], $s['columns']);
        $this->assertSame(200, $s['left_limit']);
        $this->assertSame(0, $s['depth']);
        $this->assertSame(50, $s['depth_left_limit']);
        $this->assertSame(100, $s['depth_max_seeds']);
        $this->assertSame([], $s['minus_words']);
        $this->assertFalse($s['exact_freq']);
    }

    public function testClampsAndCleans(): void
    {
        $s = WordstatSettings::normalize([
            'region_ids' => ['213', 'x', 2, 2],
            'device' => 'fridge',
            'columns' => ['right', 'middle'],
            'left_limit' => '5000',
            'depth' => 9,
            'depth_left_limit' => 0,
            'depth_max_seeds' => -3,
            'minus_words' => "Бесплатно\n своими руками ,бесплатно",
            'stop_words' => ['  Купить '],
            'exact_freq' => '1',
        ]);
        $this->assertSame([213, 2], $s['region_ids']);
        $this->assertSame('all', $s['device']);
        $this->assertSame(['right'], $s['columns']);
        $this->assertSame(2000, $s['left_limit']);
        $this->assertSame(2, $s['depth']);
        $this->assertSame(1, $s['depth_left_limit']);
        $this->assertSame(0, $s['depth_max_seeds']);
        $this->assertSame(['бесплатно', 'своими руками'], $s['minus_words']);
        $this->assertSame(['купить'], $s['stop_words']);
        $this->assertTrue($s['exact_freq']);
    }

    public function testEmptyColumnsFallBackToBoth(): void
    {
        $this->assertSame(['left'], WordstatSettings::normalize(['columns' => []])['columns']);
    }

    public function testIgnoresNonScalarInputAndFoldsYo(): void
    {
        $s = WordstatSettings::normalize([
            'device' => ['phone'],
            'minus_words' => ['Ёлка', ['x'], null],
            'stop_words' => ['a' => ['b']],
        ]);
        $this->assertSame('all', $s['device']);
        $this->assertSame(['елка'], $s['minus_words']);
        $this->assertSame([], $s['stop_words']);
    }
}
