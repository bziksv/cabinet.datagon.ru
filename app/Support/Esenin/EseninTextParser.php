<?php

namespace App\Support\Esenin;

use App\Support\TextAnalyzerStopWords;

final class EseninTextParser
{
    /**
     * @return array<int, string>
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-]*/u', $text, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @return array<string, int>
     */
    public static function wordCounts(string $text): array
    {
        $counts = [];
        foreach (self::tokenize($text) as $word) {
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<int, string>
     */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?…])\s+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static function ($sentence) {
            return $sentence !== '';
        }));
    }

    /**
     * @param array<int, string> $words
     * @return array<string, int>
     */
    public static function ngramCounts(array $words, int $n): array
    {
        if ($n < 2 || count($words) < $n) {
            return [];
        }

        $counts = [];
        $limit = count($words) - $n + 1;
        for ($i = 0; $i < $limit; $i++) {
            $slice = array_slice($words, $i, $n);
            $phrase = implode(' ', $slice);
            $counts[$phrase] = ($counts[$phrase] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Академическая тошнота (шкала Тургенева): √(Σ n²) × 100 / N.
     * n — число вхождений каждого слова, N — общее число слов.
     */
    public static function academicNausea(array $wordCounts, int $totalWords): float
    {
        if ($totalWords === 0 || $wordCounts === []) {
            return 0.0;
        }

        $sum = 0;
        foreach ($wordCounts as $count) {
            $sum += $count * $count;
        }

        return round((sqrt($sum) * 100) / $totalWords, 2);
    }

    /**
     * Тошнота словосочетаний: та же формула по биграммам.
     */
    public static function phraseNausea(array $bigramCounts, int $totalWords): float
    {
        if ($totalWords < 2 || $bigramCounts === []) {
            return 0.0;
        }

        $sum = 0;
        foreach ($bigramCounts as $count) {
            $sum += $count * $count;
        }

        return round((sqrt($sum) * 100) / max(1, $totalWords - 1), 2);
    }

    /** Классическая тошнота: √(макс. число повторов одного слова). Справочно, без штрафа. */
    public static function classicNausea(array $wordCounts): float
    {
        if ($wordCounts === []) {
            return 0.0;
        }

        return round(sqrt(max($wordCounts)), 2);
    }

    /**
     * @param array<string, int> $wordCounts
     * @return array<int, array{word: string, count: int, ratio: float}>
     */
    public static function superFrequentWords(array $wordCounts, int $totalWords): array
    {
        if ($totalWords < 20) {
            return [];
        }

        arsort($wordCounts);
        $rank = 0;
        $result = [];

        foreach ($wordCounts as $word => $count) {
            $rank++;
            if (TextAnalyzerStopWords::isPhraseStopWord($word)) {
                continue;
            }
            if ($count < 3) {
                break;
            }

            $expected = max(1.0, $totalWords / max(1, $rank));
            $ratio = $count / $expected;
            if ($ratio >= 2.5 && mb_strlen($word) > 2) {
                $result[] = [
                    'word' => $word,
                    'count' => $count,
                    'ratio' => round($ratio, 2),
                ];
            }
        }

        return $result;
    }

    public static function wateriness(array $wordCounts, int $totalWords): float
    {
        if ($totalWords === 0) {
            return 0.0;
        }

        $stop = 0;
        foreach ($wordCounts as $word => $count) {
            if (TextAnalyzerStopWords::isPhraseStopWord($word)) {
                $stop += $count;
            }
        }

        return round($stop / $totalWords, 2);
    }

    public static function informativeShare(array $wordCounts, int $totalWords): float
    {
        if ($totalWords === 0) {
            return 0.0;
        }

        $genericLookup = array_flip(array_map(static function ($word) {
            return mb_strtolower($word, 'UTF-8');
        }, config('esenin-generic-words', [])));

        $filtered = 0;
        foreach ($wordCounts as $word => $count) {
            if (TextAnalyzerStopWords::isPhraseStopWord($word) || isset($genericLookup[$word])) {
                $filtered += $count;
            }
        }

        return round(max(0, ($totalWords - $filtered) / $totalWords), 2);
    }

    public static function readabilityIndex(int $charCount, int $wordCount, int $sentenceCount): float
    {
        if ($wordCount === 0 || $sentenceCount === 0) {
            return 0.0;
        }

        $charsPerWord = $charCount / $wordCount;
        $wordsPerSentence = $wordCount / $sentenceCount;

        return round(4.71 * $charsPerWord + 0.5 * $wordsPerSentence - 21.43, 1);
    }
}
