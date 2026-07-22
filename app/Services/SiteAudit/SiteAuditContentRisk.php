<?php

namespace App\Services\SiteAudit;

/**
 * Lite content-risk: adult / negative keyword scores + повторы слова в предложении.
 * Не ML и не внешний антиплагиат — эвристики для фазы C.
 */
class SiteAuditContentRisk
{
    /**
     * @return array{
     *   adult: bool,
     *   adult_score: int,
     *   adult_hits: string[],
     *   negative: bool,
     *   negative_score: int,
     *   negative_hits: string[],
     *   word_repeat: bool,
     *   word_repeat_samples: list<array{word:string,count:int,sentence:string}>
     * }
     */
    public static function analyze(string $text): array
    {
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?: $text);
        $adultHits = self::hitList($norm, config('site_audit.content_risk_adult', []));
        $negHits = self::hitList($norm, config('site_audit.content_risk_negative', []));
        $adultThresh = max(1, (int) config('site_audit.content_risk_adult_min_hits', 2));
        $negThresh = max(1, (int) config('site_audit.content_risk_negative_min_hits', 2));
        $repeats = self::wordRepeatsInSentences($text);

        return [
            'adult' => count($adultHits) >= $adultThresh,
            'adult_score' => count($adultHits),
            'adult_hits' => array_slice($adultHits, 0, 8),
            'negative' => count($negHits) >= $negThresh,
            'negative_score' => count($negHits),
            'negative_hits' => array_slice($negHits, 0, 8),
            'word_repeat' => $repeats !== [],
            'word_repeat_samples' => array_slice($repeats, 0, 5),
        ];
    }

    /**
     * @param mixed $list
     * @return string[]
     */
    private static function hitList(string $normText, $list): array
    {
        if (! is_array($list) || $list === []) {
            return [];
        }
        $hits = [];
        foreach ($list as $term) {
            $term = mb_strtolower(trim((string) $term));
            if ($term === '' || mb_strlen($term) < 3) {
                continue;
            }
            // слово/фраза целиком
            $pattern = '/(?:^|[^\p{L}\p{N}_])' . preg_quote($term, '/') . '(?:[^\p{L}\p{N}_]|$)/u';
            if (preg_match($pattern, $normText)) {
                $hits[] = $term;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * @return list<array{word:string,count:int,sentence:string}>
     */
    private static function wordRepeatsInSentences(string $text): array
    {
        $minWordLen = max(3, (int) config('site_audit.word_repeat_min_len', 4));
        $minCount = max(2, (int) config('site_audit.word_repeat_min_count', 3));
        $parts = preg_split('/(?<=[.!?…])\s+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence) < 20) {
                continue;
            }
            if (! preg_match_all('/[\p{L}\p{N}]{' . $minWordLen . ',}/u', mb_strtolower($sentence), $m)) {
                continue;
            }
            $counts = array_count_values($m[0]);
            arsort($counts);
            foreach ($counts as $word => $c) {
                if ((int) $c < $minCount) {
                    break;
                }
                // стоп-слова пропускаем
                if (in_array($word, ['это', 'that', 'this', 'with', 'from', 'http', 'https'], true)) {
                    continue;
                }
                $out[] = [
                    'word' => (string) $word,
                    'count' => (int) $c,
                    'sentence' => mb_substr($sentence, 0, 160),
                ];
                break;
            }
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }
}
