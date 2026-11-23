<?php
namespace App\Services\Wordstat;

class WordstatPhraseFilter
{
    public static function hasMinusWord(string $phrase, array $minusWords): bool
    {
        if ($minusWords === []) {
            return false;
        }
        $haystack = ' ' . WordstatPhraseKey::make($phrase) . ' ';
        foreach ($minusWords as $word) {
            $needle = WordstatPhraseKey::make((string) $word);
            if ($needle !== '' && mb_strpos($haystack, ' ' . $needle . ' ') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function stripStopWords(string $phrase, array $stopWords): string
    {
        if ($stopWords === []) {
            return trim(preg_replace('/\s+/u', ' ', $phrase));
        }
        $stop = array_map([WordstatPhraseKey::class, 'make'], $stopWords);
        $words = preg_split('/\s+/u', trim($phrase));
        $kept = array_filter($words, fn ($w) => ! in_array(WordstatPhraseKey::make($w), $stop, true));

        return implode(' ', $kept);
    }
}
