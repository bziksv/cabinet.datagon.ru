<?php

namespace App\Support\Esenin;

use App\Morphy;

final class EseninMorphology
{
    /** @var Morphy|null */
    private static $morphy;

    /** @var array<string, string> */
    private static $lemmaCache = [];

    public static function resetCache(): void
    {
        self::$lemmaCache = [];
    }

    public static function lemma(string $word): string
    {
        $word = mb_strtolower(trim($word), 'UTF-8');
        if ($word === '') {
            return '';
        }

        if (isset(self::$lemmaCache[$word])) {
            return self::$lemmaCache[$word];
        }

        if (! preg_match('/[\p{L}]/u', $word)) {
            self::$lemmaCache[$word] = $word;

            return $word;
        }

        try {
            $base = self::morphy()->base($word);
            $lemma = $base ?: $word;
        } catch (\Throwable $e) {
            $lemma = $word;
        }

        self::$lemmaCache[$word] = $lemma;

        return $lemma;
    }

    /**
     * @param array<int, string> $tokens
     * @return array<int, string>
     */
    public static function lemmatizeTokens(array $tokens): array
    {
        $result = [];
        foreach ($tokens as $token) {
            $result[] = self::lemma($token);
        }

        return $result;
    }

    /**
     * @param array<int, string> $tokens
     * @return array<string, array{lemma: string, count: int, forms: array<string, int>}>
     */
    public static function groupByLemma(array $tokens): array
    {
        $groups = [];
        foreach ($tokens as $token) {
            $lemma = self::lemma($token);
            if (! isset($groups[$lemma])) {
                $groups[$lemma] = [
                    'lemma' => $lemma,
                    'count' => 0,
                    'forms' => [],
                ];
            }
            $groups[$lemma]['count']++;
            $groups[$lemma]['forms'][$token] = ($groups[$lemma]['forms'][$token] ?? 0) + 1;
        }

        return $groups;
    }

    /**
     * @param array<int, string> $tokens
     * @return array<string, int>
     */
    public static function lemmaCounts(array $tokens): array
    {
        $counts = [];
        foreach ($tokens as $token) {
            $lemma = self::lemma($token);
            $counts[$lemma] = ($counts[$lemma] ?? 0) + 1;
        }

        return $counts;
    }

    private static function morphy(): Morphy
    {
        if (self::$morphy === null) {
            self::$morphy = new Morphy();
        }

        return self::$morphy;
    }
}
