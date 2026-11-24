<?php
namespace Tests\Unit\Wordstat;

use App\Services\Wordstat\WordstatInputParser;
use App\Services\Wordstat\WordstatPhraseKey;
use PHPUnit\Framework\TestCase;

class WordstatInputParserTest extends TestCase
{
    public function testPlainLinesGoToDefaultGroup(): void
    {
        $r = WordstatInputParser::parse("пластиковые окна\n\n  Натяжные   потолки \n", 'Без группы');
        $this->assertSame([
            ['group' => 'Без группы', 'phrase' => 'пластиковые окна'],
            ['group' => 'Без группы', 'phrase' => 'Натяжные потолки'],
        ], $r['seeds']);
        $this->assertSame([], $r['rejected']);
    }

    public function testColonHeaderAndTabFormats(): void
    {
        $text = "Окна: пластиковые окна\n# Потолки\nнатяжные потолки\nпотолки армстронг\nДвери\tмежкомнатные двери";
        $r = WordstatInputParser::parse($text, 'Без группы');
        $this->assertSame([
            ['group' => 'Окна', 'phrase' => 'пластиковые окна'],
            ['group' => 'Потолки', 'phrase' => 'натяжные потолки'],
            ['group' => 'Потолки', 'phrase' => 'потолки армстронг'],
            ['group' => 'Двери', 'phrase' => 'межкомнатные двери'],
        ], $r['seeds']);
    }

    public function testDuplicatesInSameGroupDropped(): void
    {
        $r = WordstatInputParser::parse("окна пвх\nОкна  ПВХ\nА: окна пвх", 'Б');
        $this->assertCount(2, $r['seeds']);
    }

    public function testRejectsLongAndWordy(): void
    {
        $r = WordstatInputParser::parse(str_repeat('а', 401) . "\nодин два три четыре пять шесть семь восемь", 'Б');
        $this->assertSame([], $r['seeds']);
        $this->assertSame('too_long', $r['rejected'][0]['reason']);
        $this->assertSame('too_many_words', $r['rejected'][1]['reason']);
    }

    public function testSkipsOperatorOnlyLinesAndKeepsUrls(): void
    {
        $r = WordstatInputParser::parse("-дешево\nhttps://site.ru/okna", 'Б');
        $this->assertSame([['group' => 'Б', 'phrase' => 'https://site.ru/okna']], $r['seeds']);
    }

    public function testPhraseKey(): void
    {
        $this->assertSame('купить елку', WordstatPhraseKey::make('  "!Купить  +ёлку" '));
        $this->assertSame('окна пвх', WordstatPhraseKey::make('окна [пвх] -дешево'));
    }

    public function testEmptyHeaderIsReported(): void
    {
        $r = WordstatInputParser::parse("офтальмоскоп\n#кератометр", 'Без группы');
        $this->assertSame([['group' => 'Без группы', 'phrase' => 'офтальмоскоп']], $r['seeds']);
        $this->assertSame([['line' => '#кератометр', 'reason' => 'empty_group']], $r['rejected']);

        $r = WordstatInputParser::parse("# Окна\nокна пвх\n# Двери\nВходные: двери входные", 'Без группы');
        $this->assertCount(2, $r['seeds']);
        $this->assertSame('# Двери', $r['rejected'][0]['line']);
    }
}
