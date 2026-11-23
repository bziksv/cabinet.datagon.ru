<?php
namespace Tests\Unit\Wordstat;

use App\Services\Wordstat\WordstatPhraseFilter;
use PHPUnit\Framework\TestCase;

class WordstatPhraseFilterTest extends TestCase
{
    public function testMinusWordMatchesWholeWordsOnly(): void
    {
        $this->assertTrue(WordstatPhraseFilter::hasMinusWord('окна бесплатно москва', ['бесплатно']));
        $this->assertFalse(WordstatPhraseFilter::hasMinusWord('окна бесплатного монтажа', ['бесплатно']));
        $this->assertTrue(WordstatPhraseFilter::hasMinusWord('потолок своими руками видео', ['своими руками']));
        $this->assertTrue(WordstatPhraseFilter::hasMinusWord('Ёлка Бесплатно', ['бесплатно']));
    }

    public function testStripStopWords(): void
    {
        $this->assertSame('пластиковые окна', WordstatPhraseFilter::stripStopWords('купить пластиковые окна недорого', ['купить', 'недорого']));
        $this->assertSame('окна', WordstatPhraseFilter::stripStopWords('окна', []));
    }
}
