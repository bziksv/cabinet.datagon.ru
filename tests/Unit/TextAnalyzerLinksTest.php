<?php

namespace Tests\Unit;

use App\TextAnalyzer;
use PHPUnit\Framework\TestCase;

class TextAnalyzerLinksTest extends TestCase
{
    /**
     * @dataProvider tagsThatMustNotBeTreatedAsAnchors
     */
    public function testTagStartingWithAIsKeptInTextAndAnchorIsSeparated(string $tag): void
    {
        $html = '<' . $tag . '>текст</' . $tag . '><p>ещё</p><a href="#">ссылка</a>';

        $text = TextAnalyzer::deleteEverythingExceptCharacters(TextAnalyzer::clearHTMLFromLinks($html));
        $anchors = TextAnalyzer::getLinkText($html);

        $this->assertStringContainsString('текст', $text);
        $this->assertStringContainsString('ещё', $text);
        $this->assertStringNotContainsString('ссылка', $text);
        $this->assertStringContainsString('ссылка', $anchors);
        $this->assertStringNotContainsString('текст', $anchors);
        $this->assertStringNotContainsString('ещё', $anchors);
    }

    public function tagsThatMustNotBeTreatedAsAnchors(): array
    {
        return [
            'aside' => ['aside'],
            'audio' => ['audio'],
            'abbr' => ['abbr'],
            'address' => ['address'],
            'article' => ['article'],
        ];
    }

    public function testAnchorTagWithNewlineAndUppercaseStillExtractsAnchor(): void
    {
        $html = "<p>абзац</p><A\nHREF=\"x\">Якорь</a>";

        $text = TextAnalyzer::deleteEverythingExceptCharacters(TextAnalyzer::clearHTMLFromLinks($html));
        $anchors = TextAnalyzer::getLinkText($html);

        $this->assertStringContainsString('абзац', $text);
        $this->assertStringNotContainsString('Якорь', $text);
        $this->assertStringNotContainsString('якорь', $text);
        $this->assertStringContainsString('Якорь', $anchors);
    }

    public function testNestedMarkupInsideAnchorGoesToAnchorsOnly(): void
    {
        $html = '<article>статья</article><a href><span>x</span></a>';

        $text = TextAnalyzer::deleteEverythingExceptCharacters(TextAnalyzer::clearHTMLFromLinks($html));
        $anchors = TextAnalyzer::getLinkText($html);

        $this->assertStringContainsString('статья', $text);
        $this->assertStringNotContainsString('x', $text);
        $this->assertSame('x', trim($anchors));
    }

    public function testAsideBeforeFirstAnchorDoesNotSwallowTheRestOfThePage(): void
    {
        $body = trim(str_repeat('слово ', 2500));
        $html = '<aside>' . $body . '</aside><p>хвост</p><a href="#">ссылка</a>';

        $cleared = TextAnalyzer::clearHTMLFromLinks($html);

        $this->assertStringContainsString('<p>хвост</p>', $cleared);
        $this->assertStringNotContainsString('ссылка', $cleared);
        $this->assertGreaterThan(20000, mb_strlen(strip_tags($cleared)));
    }
}
