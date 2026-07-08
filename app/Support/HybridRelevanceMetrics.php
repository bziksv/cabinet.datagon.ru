<?php

namespace App\Support;

use App\Relevance;

final class HybridRelevanceMetrics
{
    /**
     * Okapi BM25 (ln IDF).
     */
    public static function bm25(
        float $termCount,
        float $docLength,
        float $avgDocLength,
        int $documentFrequency,
        int $documentCount,
        float $k1 = 1.2,
        float $b = 0.75
    ): float {
        $documentCount = max(1, $documentCount);
        $documentFrequency = max(1, min($documentFrequency, $documentCount));
        $avgDocLength = max(1.0, $avgDocLength);
        $docLength = max(0.0, $docLength);
        $termCount = max(0.0, $termCount);

        $idf = log(1 + ($documentCount - $documentFrequency + 0.5) / ($documentFrequency + 0.5));
        $denominator = $termCount + $k1 * (1 - $b + $b * $docLength / $avgDocLength);
        $tf = $denominator > 0 ? ($termCount * ($k1 + 1)) / $denominator : 0.0;

        return round($idf * $tf, 7);
    }

    private const HYBRID_TOP_SCALE = 10.0;
    private const HYBRID_SITE_SCALE = 5.0;

    private const HYBRID_BM25_TOP_COVERAGE_POWER = 1.4;
    private const HYBRID_BM25_TOP_CORPUS_IDF_POWER = 0.3;
    private const HYBRID_BM25_TOP_SCALE = 0.118;
    private const HYBRID_BM25_SITE_BM25_SCALE = 0.025;
    private const HYBRID_BM25_SITE_LOW_REPEATS = 3;
    private const HYBRID_BM25_SITE_HIGH_REPEATS = 20;
    private const HYBRID_BM25_SITE_LOW_FACTOR = 0.95;
    private const HYBRID_BM25_SITE_HIGH_FACTOR = 0.99;

    /**
     * Гибридный TF-IDF ТОП: tf × log10(corpus/count) × (df/N) × scale.
     * Даёт порядок как у конкурента: частые слова с широким покрытием выше.
     */
    public static function hybridTfidfTop(
        float $termCount,
        float $zoneWords,
        int $documentFrequency,
        int $documentCount
    ): float {
        $termCount = max(0.0, $termCount);
        $zoneWords = max(1.0, $zoneWords);
        $documentCount = max(1, $documentCount);
        $documentFrequency = max(1, min($documentFrequency, $documentCount));

        if ($termCount <= 0) {
            return 0.0;
        }

        $tf = $termCount / $zoneWords;
        $corpusIdf = log10($zoneWords / $termCount);
        $coverage = $documentFrequency / $documentCount;

        return round($tf * $corpusIdf * $coverage * self::HYBRID_TOP_SCALE, 7);
    }

    /**
     * Гибридный TF-IDF ваш сайт: tf_сайта × sqrt(log10(corpus/count)) × (1 + df/N) × scale.
     */
    public static function hybridTfidfSite(
        float $mainRepeats,
        float $topTermCount,
        float $mainPageWords,
        float $zoneWords,
        int $documentFrequency,
        int $documentCount
    ): float {
        $mainRepeats = max(0.0, $mainRepeats);
        $mainPageWords = max(1.0, $mainPageWords);
        $zoneWords = max(1.0, $zoneWords);
        $topTermCount = max(1.0, $topTermCount);
        $documentCount = max(1, $documentCount);
        $documentFrequency = max(1, min($documentFrequency, $documentCount));

        if ($mainRepeats <= 0) {
            return 0.0;
        }

        $tfSite = $mainRepeats / $mainPageWords;
        $corpusIdf = log10($zoneWords / $topTermCount);
        $coverage = $documentFrequency / $documentCount;

        return round($tfSite * sqrt(max(0.0, $corpusIdf)) * (1 + $coverage) * self::HYBRID_SITE_SCALE, 7);
    }

    /**
     * BM25-TF насыщение (k1 без нормализации длины документа).
     */
    public static function bm25TermFrequency(float $termCount, float $k1 = 1.2): float
    {
        $termCount = max(0.0, $termCount);

        if ($termCount <= 0) {
            return 0.0;
        }

        return ($termCount * ($k1 + 1)) / ($termCount + $k1);
    }

    /**
     * BM25-TF с нормализацией длины документа (Okapi).
     */
    public static function bm25DocumentTermFrequency(
        float $termCount,
        float $docLength,
        float $avgDocLength,
        float $k1 = 1.2,
        float $b = 0.75
    ): float {
        $termCount = max(0.0, $termCount);
        $avgDocLength = max(1.0, $avgDocLength);
        $docLength = max(0.0, $docLength);

        if ($termCount <= 0) {
            return 0.0;
        }

        $denominator = $termCount + $k1 * (1 - $b + $b * $docLength / $avgDocLength);

        return $denominator > 0 ? ($termCount * ($k1 + 1)) / $denominator : 0.0;
    }

    /**
     * Гибридный BM25 ТОП: насыщенный tf × log10(corpus/count)^p × (df/N)^γ × scale.
     */
    public static function hybridBm25Top(
        float $avgTopRepeats,
        float $topTermCount,
        float $zoneWords,
        int $documentFrequency,
        int $documentCount
    ): float {
        $avgTopRepeats = max(0.0, $avgTopRepeats);
        $zoneWords = max(1.0, $zoneWords);
        $topTermCount = max(1.0, $topTermCount);
        $documentCount = max(1, $documentCount);
        $documentFrequency = max(1, min($documentFrequency, $documentCount));

        if ($avgTopRepeats <= 0) {
            return 0.0;
        }

        $corpusIdf = log10($zoneWords / $topTermCount);
        $coverage = $documentFrequency / $documentCount;
        $tf = self::bm25TermFrequency($avgTopRepeats);

        return round(
            $tf
            * pow(max(0.0, $corpusIdf), self::HYBRID_BM25_TOP_CORPUS_IDF_POWER)
            * pow($coverage, self::HYBRID_BM25_TOP_COVERAGE_POWER)
            * self::HYBRID_BM25_TOP_SCALE,
            7
        );
    }

    /**
     * Гибридный BM25 ваш сайт: для редких повторений ≈ TF-IDF сайт,
     * для частых — BM25-TF с корпусным idf и покрытием.
     */
    public static function hybridBm25Site(
        float $mainRepeats,
        float $topTermCount,
        float $mainPageWords,
        float $zoneWords,
        float $avgCompetitorDocWords,
        int $documentFrequency,
        int $documentCount,
        float $hybridTfidfSite
    ): float {
        $mainRepeats = max(0.0, $mainRepeats);
        $mainPageWords = max(1.0, $mainPageWords);
        $zoneWords = max(1.0, $zoneWords);
        $topTermCount = max(1.0, $topTermCount);
        $avgCompetitorDocWords = max(1.0, $avgCompetitorDocWords);
        $documentCount = max(1, $documentCount);
        $documentFrequency = max(1, min($documentFrequency, $documentCount));

        if ($mainRepeats <= 0) {
            return 0.0;
        }

        if ($mainRepeats <= self::HYBRID_BM25_SITE_LOW_REPEATS) {
            return round($hybridTfidfSite * self::HYBRID_BM25_SITE_LOW_FACTOR, 7);
        }

        if ($mainRepeats >= self::HYBRID_BM25_SITE_HIGH_REPEATS) {
            return round($hybridTfidfSite * self::HYBRID_BM25_SITE_HIGH_FACTOR, 7);
        }

        $corpusIdf = log10($zoneWords / $topTermCount);
        $coverage = $documentFrequency / $documentCount;
        $tf = self::bm25DocumentTermFrequency($mainRepeats, $mainPageWords, $avgCompetitorDocWords);

        return round(
            $tf
            * sqrt(max(0.0, $corpusIdf))
            * (1 + $coverage)
            * self::HYBRID_BM25_SITE_BM25_SCALE,
            7
        );
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, int|float> $corpusZones
     */
    public static function applyTableBm25ToWordStats(array &$stats, array $corpusZones): void
    {
        $documentCount = max(1, (int) ($corpusZones['documentCount'] ?? $corpusZones['competitorSiteCount'] ?? 1));
        $documentFrequency = self::resolveDocumentFrequency($stats);
        $avgCompetitorDocWords = max(1.0, (float) ($corpusZones['avgCompetitorDocWords'] ?? 1));

        $rawTopTotal = self::resolveRawTopCount(
            $stats,
            (float) ($stats['tf'] ?? 0),
            (float) $corpusZones['competitorCorpusWords']
        );
        $avgTopRepeats = $documentCount > 0 ? $rawTopTotal / $documentCount : 0.0;

        $stats['bm25Top'] = self::hybridBm25Top(
            $avgTopRepeats,
            $rawTopTotal,
            (float) $corpusZones['competitorCorpusWords'],
            $documentFrequency,
            $documentCount
        );
        $stats['bm25Site'] = self::hybridBm25Site(
            (float) ($stats['totalRepeatMainPage'] ?? 0),
            $rawTopTotal,
            (float) $corpusZones['mainPageWords'],
            (float) $corpusZones['competitorCorpusWords'],
            $avgCompetitorDocWords,
            $documentFrequency,
            $documentCount,
            (float) ($stats['tfidfSite'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, int|float> $corpusZones
     */
    public static function applyTableTfidfToWordStats(array &$stats, array $corpusZones): void
    {
        $documentCount = max(1, (int) ($corpusZones['documentCount'] ?? $corpusZones['competitorSiteCount'] ?? 1));
        $documentFrequency = self::resolveDocumentFrequency($stats);

        $rawTopTotal = self::resolveRawTopCount($stats, (float) ($stats['tf'] ?? 0), (float) $corpusZones['competitorCorpusWords']);
        $rawTopText = self::resolveTopZoneCount($stats, 'countTopText', 'avgInText', $corpusZones);
        $rawTopLink = self::resolveTopZoneCount($stats, 'countTopLink', 'avgInLink', $corpusZones);

        $stats['tfidfTop'] = self::hybridTfidfTop(
            $rawTopTotal,
            (float) $corpusZones['competitorCorpusWords'],
            $documentFrequency,
            $documentCount
        );
        $stats['tfidfTopText'] = self::hybridTfidfTop(
            $rawTopText,
            (float) $corpusZones['competitorTextWords'],
            $documentFrequency,
            $documentCount
        );
        $stats['tfidfTopLink'] = self::hybridTfidfTop(
            $rawTopLink,
            (float) $corpusZones['competitorLinkWords'],
            $documentFrequency,
            $documentCount
        );

        $stats['tfidfSite'] = self::hybridTfidfSite(
            (float) ($stats['totalRepeatMainPage'] ?? 0),
            $rawTopTotal,
            (float) $corpusZones['mainPageWords'],
            (float) $corpusZones['competitorCorpusWords'],
            $documentFrequency,
            $documentCount
        );
        $stats['tfidfSiteText'] = self::hybridTfidfSite(
            (float) ($stats['repeatInTextMainPage'] ?? 0),
            max(1.0, $rawTopText),
            (float) $corpusZones['mainPageTextWords'],
            (float) $corpusZones['competitorTextWords'],
            $documentFrequency,
            $documentCount
        );
        $stats['tfidfSiteLink'] = self::hybridTfidfSite(
            (float) ($stats['repeatInLinkMainPage'] ?? 0),
            max(1.0, $rawTopLink),
            (float) $corpusZones['mainPageLinkWords'],
            (float) $corpusZones['competitorLinkWords'],
            $documentFrequency,
            $documentCount
        );
    }

    /**
     * @param array<string, mixed> $stats
     */
    private static function resolveDocumentFrequency(array $stats): int
    {
        if (!empty($stats['occurrences']) && is_array($stats['occurrences'])) {
            return max(1, count($stats['occurrences']));
        }

        return max(1, (int) ($stats['numberOccurrences'] ?? 1));
    }

    /**
     * @param array<string, mixed> $stats
     */
    private static function resolveRawTopCount(array $stats, float $tf, float $corpusWords): float
    {
        if (!empty($stats['occurrences']) && is_array($stats['occurrences'])) {
            return (float) array_sum($stats['occurrences']);
        }

        if ($tf > 0 && $corpusWords > 0) {
            return $tf * $corpusWords;
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, int|float> $corpusZones
     */
    private static function resolveTopZoneCount(array $stats, string $countKey, string $avgKey, array $corpusZones): float
    {
        $count = (float) ($stats[$countKey] ?? 0);
        if ($count > 0) {
            return $count;
        }

        if (isset($stats[$avgKey], $corpusZones['competitorSiteCount'])) {
            return (float) $stats[$avgKey] * (float) $corpusZones['competitorSiteCount'];
        }

        return 0.0;
    }

    /**
     * @return array{
     *     mainPageWords:int,
     *     mainPageTextWords:int,
     *     mainPageLinkWords:int,
     *     competitorCorpusWords:int,
     *     competitorTextWords:int,
     *     competitorLinkWords:int,
     *     avgCompetitorDocWords:float,
     *     documentCount:int,
     *     competitorSiteCount:int
     * }
     */
    public static function corpusZoneStatsFromData(array $data): array
    {
        $base = self::corpusStatsFromData($data);
        $mainPage = is_array($data['main_page'] ?? null) ? $data['main_page'] : [];

        $mainPageTextWords = max(1, (int) ($mainPage['mainPageTextWords'] ?? 0));
        $mainPageLinkWords = max(1, (int) ($mainPage['mainPageLinkWords'] ?? 0));
        $competitorTextWords = max(1, (int) ($mainPage['competitorTextWords'] ?? 0));
        $competitorLinkWords = max(1, (int) ($mainPage['competitorLinkWords'] ?? 0));

        if ($mainPageTextWords <= 1 && $mainPageLinkWords <= 1) {
            $mainPageTextWords = $base['mainPageWords'];
            $mainPageLinkWords = $base['mainPageWords'];
        }

        if ($competitorTextWords <= 1 && $competitorLinkWords <= 1) {
            $competitorTextWords = $base['competitorCorpusWords'];
            $competitorLinkWords = $base['competitorCorpusWords'];
        }

        return array_merge($base, [
            'mainPageTextWords' => $mainPageTextWords,
            'mainPageLinkWords' => $mainPageLinkWords,
            'competitorTextWords' => $competitorTextWords,
            'competitorLinkWords' => $competitorLinkWords,
            'competitorSiteCount' => max(1, $base['documentCount']),
            'documentCount' => max(1, $base['documentCount']),
        ]);
    }

    public static function countWordsInText(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * @return array{competitors:array<string,array<string,float>>,mainPage:array<string,array<string,float>>}
     */
    public static function buildTfIdfCloudLookups(array $data): array
    {
        $lookups = [
            'competitors' => ['totalTf' => [], 'textTf' => [], 'linkTf' => []],
            'mainPage' => ['totalTf' => [], 'textTf' => [], 'linkTf' => []],
        ];

        foreach (['totalTf', 'textTf', 'linkTf'] as $zone) {
            if (!empty($data['clouds_competitors'][$zone]) && is_array($data['clouds_competitors'][$zone])) {
                $lookups['competitors'][$zone] = self::cloudZoneLookup($data['clouds_competitors'][$zone]);
            }
            if (!empty($data['clouds_main_page'][$zone]) && is_array($data['clouds_main_page'][$zone])) {
                $lookups['mainPage'][$zone] = self::cloudZoneLookup($data['clouds_main_page'][$zone]);
            }
        }

        return $lookups;
    }

    /**
     * @param array<int|string, mixed> $cloud
     * @return array<string, float>
     */
    private static function cloudZoneLookup(array $cloud): array
    {
        $lookup = [];

        foreach ($cloud as $item) {
            if (!is_array($item) || empty($item['text'])) {
                continue;
            }

            $key = mb_strtolower((string) $item['text'], 'UTF-8');
            $score = self::cloudItemTfidfScore($item);
            $lookup[$key] = max($lookup[$key] ?? 0, $score);
        }

        return $lookup;
    }

    /**
     * TF×IDF из облака: tfidfScore (зона), не weight (Tf для отрисовки).
     *
     * @param array<string, mixed> $item
     */
    public static function cloudItemTfidfScore(array $item): float
    {
        if (isset($item['tfidfScore']) && is_numeric($item['tfidfScore'])) {
            return (float) $item['tfidfScore'];
        }

        if (isset($item['html']['title']) && is_numeric($item['html']['title'])) {
            return (float) $item['html']['title'];
        }

        if (isset($item['tf'], $item['idf']) && is_numeric($item['tf']) && is_numeric($item['idf'])) {
            return TfidfMetrics::score((float) $item['tf'], (float) $item['idf']);
        }

        return (float) ($item['weight'] ?? 0);
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $wordForm
     */
    public static function applyCloudTfidfToWordStats(
        array &$stats,
        string $wordKey,
        array $cloudLookups,
        array $wordForm = [],
        bool $isTotal = false
    ): void {
        $key = mb_strtolower($wordKey, 'UTF-8');

        $stats['tfidfTop'] = self::resolveCloudScore($cloudLookups['competitors']['totalTf'] ?? [], $key, $wordForm, $isTotal);
        $stats['tfidfTopText'] = self::resolveCloudScore($cloudLookups['competitors']['textTf'] ?? [], $key, $wordForm, $isTotal);
        $stats['tfidfTopLink'] = self::resolveCloudScore($cloudLookups['competitors']['linkTf'] ?? [], $key, $wordForm, $isTotal);

        $stats['tfidfSite'] = self::resolveCloudScore($cloudLookups['mainPage']['totalTf'] ?? [], $key, $wordForm, $isTotal);
        $stats['tfidfSiteText'] = self::resolveCloudScore($cloudLookups['mainPage']['textTf'] ?? [], $key, $wordForm, $isTotal);
        $stats['tfidfSiteLink'] = self::resolveCloudScore($cloudLookups['mainPage']['linkTf'] ?? [], $key, $wordForm, $isTotal);
    }

    /**
     * @param array<string, float> $zoneLookup
     * @param array<string, mixed> $wordForm
     */
    private static function resolveCloudScore(array $zoneLookup, string $key, array $wordForm, bool $isTotal): float
    {
        if (isset($zoneLookup[$key])) {
            return (float) $zoneLookup[$key];
        }

        if (!$isTotal) {
            return 0.0;
        }

        $max = 0.0;
        foreach ($wordForm as $word => $data) {
            if ($word === 'total' || !is_array($data)) {
                continue;
            }

            $wordKey = mb_strtolower((string) $word, 'UTF-8');
            if (isset($zoneLookup[$wordKey])) {
                $max = max($max, (float) $zoneLookup[$wordKey]);
            }
        }

        if ($max > 0) {
            return $max;
        }

        foreach ($zoneLookup as $cloudWord => $score) {
            if (self::wordsShareLemma($key, $cloudWord)) {
                $max = max($max, (float) $score);
            }
        }

        return $max;
    }

    private static function wordsShareLemma(string $left, string $right): bool
    {
        $left = mb_strtolower(trim($left), 'UTF-8');
        $right = mb_strtolower(trim($right), 'UTF-8');

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        if (mb_strlen($left) >= 4 && mb_strlen($right) >= 4) {
            if (mb_strpos($left, $right) !== false || mb_strpos($right, $left) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{mainPageWords:int,competitorCorpusWords:int,avgCompetitorDocWords:float,documentCount:int}
     */
    public static function corpusStatsFromData(array $data): array
    {
        $mainPage = is_array($data['main_page'] ?? null) ? $data['main_page'] : [];
        $mainPageWords = max(1, (int) ($mainPage['countWords'] ?? 0));
        $competitorCorpusWords = max(0, (int) ($mainPage['competitorCorpusWords'] ?? 0));
        $avgCompetitorDocWords = max(0.0, (float) ($mainPage['avgCompetitorDocWords'] ?? 0));
        $documentCount = Relevance::competitorDocumentCountFromSites($data['sites'] ?? []);

        if ($competitorCorpusWords <= 0 && !empty($data['unigram_table']) && is_array($data['unigram_table'])) {
            foreach ($data['unigram_table'] as $wordForm) {
                if (!isset($wordForm['total']) || !is_array($wordForm['total'])) {
                    continue;
                }

                $tf = (float) ($wordForm['total']['tf'] ?? 0);
                $rawCount = array_sum($wordForm['total']['occurrences'] ?? []);
                if ($tf > 0 && $rawCount > 0) {
                    $competitorCorpusWords = (int) round($rawCount / $tf);
                    break;
                }
            }
        }

        $competitorCorpusWords = max(1, $competitorCorpusWords);

        if ($avgCompetitorDocWords <= 0) {
            $avgCompetitorDocWords = $competitorCorpusWords / max(1, $documentCount);
        }

        return [
            'mainPageWords' => $mainPageWords,
            'competitorCorpusWords' => $competitorCorpusWords,
            'avgCompetitorDocWords' => $avgCompetitorDocWords,
            'documentCount' => $documentCount,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, int|float> $corpusZones
     *
     * @deprecated Используйте applyTableBm25ToWordStats() после applyTableTfidfToWordStats().
     */
    public static function enrichBm25Stats(array &$stats, array $corpusZones, bool $isTotal = false): void
    {
        self::applyTableBm25ToWordStats($stats, $corpusZones);
    }
}
