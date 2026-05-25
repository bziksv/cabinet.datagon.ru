<?php

namespace App\Services\Competitor;

use App\Support\CompetitorSearchRegions;
use App\Support\CompetitorSerpDomainFilter;

/**
 * Сравнение выдачи между регионами: геозависимость запросов.
 * При нескольких ПС — отдельный расчёт и вывод по Яндексу и Google, без смешивания пар.
 */
class CompetitorGeoDependency
{
    /**
     * @param array<string, array<string, mixed>> $byRegion
     * @param array<int, array{key?: string, engine?: string, id?: string, tabLabel?: string, name?: string}> $regionsMeta
     */
    public function analyze(array $byRegion, array $regionsMeta): ?array
    {
        $groups = $this->groupRegionsByEngine($regionsMeta, $byRegion);
        $enginePayloads = [];

        foreach ($this->engineOrder($groups) as $engine) {
            if (! isset($groups[$engine]) || count($groups[$engine]['regionMap']) < 2) {
                continue;
            }

            $payload = $this->analyzeRegionGroup($byRegion, $groups[$engine]['regionMap']);
            if ($payload === null) {
                continue;
            }

            $enginePayloads[] = array_merge($payload, [
                'engine' => $engine,
                'engine_label' => CompetitorSearchRegions::engineLabel($engine),
            ]);
        }

        if (count($enginePayloads) === 0) {
            return null;
        }

        if (count($enginePayloads) === 1) {
            return $enginePayloads[0];
        }

        return [
            'mode' => 'by_engine',
            'engines' => $enginePayloads,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $byRegion
     * @param array<string, string> $regionMap
     */
    protected function analyzeRegionGroup(array $byRegion, array $regionMap): ?array
    {
        if (count($regionMap) < 2) {
            return null;
        }

        $phrases = $this->collectPhrases($byRegion, $regionMap);
        if (count($phrases) === 0) {
            return null;
        }

        $independentMin = (float) config('cabinet-competitor-analysis.geo_independent_min_overlap', 0.4);
        $dependentMax = (float) config('cabinet-competitor-analysis.geo_dependent_max_overlap', 0.35);
        $regionPairs = $this->buildRegionPairs($regionMap);
        $maxSharedUrls = (int) config('cabinet-competitor-analysis.geo_max_shared_urls_per_pair', 12);

        $phraseRows = [];
        $overlapForAvg = [];
        $counts = [
            'geo_independent' => 0,
            'geo_dependent' => 0,
            'partial' => 0,
            'skipped' => 0,
        ];

        foreach ($phrases as $phrase) {
            $urlSetsByRegion = [];
            foreach (array_keys($regionMap) as $regionKey) {
                $sites = $byRegion[$regionKey]['analysedSites'] ?? [];
                $urlSetsByRegion[$regionKey] = $this->urlSetForPhrase(
                    is_array($sites) ? $sites : [],
                    $phrase
                );
            }

            $pairDetails = $this->pairwiseDetailsForPhrase($urlSetsByRegion, $regionPairs, $maxSharedUrls);
            $overlap = $this->avgFromPairDetails($pairDetails);
            if ($overlap === null) {
                $counts['skipped']++;
                $phraseRows[] = [
                    'phrase' => $phrase,
                    'status' => 'skipped',
                    'overlap' => null,
                    'overlap_pct' => null,
                    'pairs' => $pairDetails,
                    'urls_by_region' => array_map(static function (array $urls) {
                        return count($urls);
                    }, $urlSetsByRegion),
                ];

                continue;
            }

            $status = $this->classifyPhrase($overlap, $independentMin, $dependentMax);
            $counts[$status]++;
            $overlapForAvg[] = $overlap;

            $phraseRows[] = [
                'phrase' => $phrase,
                'status' => $status,
                'overlap' => round($overlap, 3),
                'overlap_pct' => (int) round($overlap * 100),
                'pairs' => $pairDetails,
                'urls_by_region' => array_map(static function (array $urls) {
                    return count($urls);
                }, $urlSetsByRegion),
            ];
        }

        usort($phraseRows, function ($a, $b) {
            $aj = $a['overlap'] ?? -1;
            $bj = $b['overlap'] ?? -1;

            return $aj <=> $bj;
        });

        $scoredPhraseCount = count($overlapForAvg);
        $verdict = $this->overallVerdict($counts, $scoredPhraseCount);
        $avgOverlap = $scoredPhraseCount > 0
            ? array_sum($overlapForAvg) / $scoredPhraseCount
            : 0.0;

        $excluded = CompetitorSerpDomainFilter::excludedDomainsBreakdown();

        return [
            'verdict' => $verdict,
            'overlap_metric' => 'mean',
            'avg_overlap' => round($avgOverlap, 3),
            'avg_overlap_pct' => (int) round($avgOverlap * 100),
            'excludes_aggregators' => true,
            'excluded_domains' => $excluded['all'],
            'excluded_domains_from_settings' => $excluded['from_settings'],
            'excluded_domains_defaults' => $excluded['from_defaults'],
            'region_count' => count($regionMap),
            'phrase_count' => count($phraseRows),
            'counts' => $counts,
            'regions' => array_values(array_map(static function ($key, $label) {
                return ['key' => $key, 'label' => $label];
            }, array_keys($regionMap), $regionMap)),
            'region_pairs' => $regionPairs,
            'phrases' => $phraseRows,
            'thresholds' => [
                'independent_min' => $independentMin,
                'dependent_max' => $dependentMax,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $regionsMeta
     * @param array<string, array<string, mixed>> $byRegion
     * @return array<string, array{regionMap: array<string, string>}>
     */
    protected function groupRegionsByEngine(array $regionsMeta, array $byRegion): array
    {
        $groups = [];

        foreach ($regionsMeta as $region) {
            $engine = CompetitorSearchRegions::normalizeEngine($region['engine'] ?? 'yandex');
            $id = (string) ($region['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $key = (string) ($region['key'] ?? CompetitorSearchRegions::regionKey($engine, $id));
            if (! isset($byRegion[$key])) {
                continue;
            }
            if (! isset($groups[$engine])) {
                $groups[$engine] = ['regionMap' => []];
            }
            $label = (string) ($region['tabLabel'] ?? $region['name'] ?? $region['text'] ?? $id);
            $groups[$engine]['regionMap'][$key] = $label;
        }

        if (count($groups) === 0) {
            foreach (array_keys($byRegion) as $key) {
                $parts = explode('|', $key, 2);
                $engine = CompetitorSearchRegions::normalizeEngine($parts[0] ?? 'yandex');
                if (! isset($groups[$engine])) {
                    $groups[$engine] = ['regionMap' => []];
                }
                $groups[$engine]['regionMap'][$key] = $key;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, array{regionMap: array<string, string>}> $groups
     * @return array<int, string>
     */
    protected function engineOrder(array $groups): array
    {
        $preferred = ['yandex', 'google'];
        $order = [];
        foreach ($preferred as $engine) {
            if (isset($groups[$engine])) {
                $order[] = $engine;
            }
        }
        foreach (array_keys($groups) as $engine) {
            if (! in_array($engine, $order, true)) {
                $order[] = $engine;
            }
        }

        return $order;
    }

    /**
     * @param array<int, array<string, mixed>> $regionsMeta
     * @param array<string, array<string, mixed>> $byRegion
     * @return array<string, string> key => label
     */
    protected function resolveRegionMap(array $regionsMeta, array $byRegion): array
    {
        $map = [];

        foreach ($regionsMeta as $region) {
            $engine = CompetitorSearchRegions::normalizeEngine($region['engine'] ?? 'yandex');
            $id = (string) ($region['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $key = (string) ($region['key'] ?? CompetitorSearchRegions::regionKey($engine, $id));
            if (! isset($byRegion[$key])) {
                continue;
            }
            $label = (string) ($region['tabLabel'] ?? $region['name'] ?? $region['text'] ?? $id);
            $map[$key] = $label;
        }

        if (count($map) < 2) {
            foreach (array_keys($byRegion) as $key) {
                if (! isset($map[$key])) {
                    $map[$key] = $key;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, array<string, mixed>> $byRegion
     * @param array<string, string> $regionMap
     * @return array<int, string>
     */
    protected function collectPhrases(array $byRegion, array $regionMap): array
    {
        $phrases = [];
        foreach (array_keys($regionMap) as $regionKey) {
            $sites = $byRegion[$regionKey]['analysedSites'] ?? [];
            if (! is_array($sites)) {
                continue;
            }
            foreach (array_keys($sites) as $phrase) {
                if (is_string($phrase) && $phrase !== '') {
                    $phrases[$phrase] = true;
                }
            }
        }

        return array_keys($phrases);
    }

    /**
     * @param array<string, array<string, mixed>> $analysedSites
     * @return array<int, string>
     */
    protected function urlSetForPhrase(array $analysedSites, string $phrase): array
    {
        $sites = $analysedSites[$phrase] ?? [];
        if (! is_array($sites)) {
            return [];
        }

        $urls = [];
        foreach (array_keys($sites) as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }
            $normalized = $this->normalizeSerpUrl($url);
            if ($normalized === '' || CompetitorSerpDomainFilter::isExcludedNormalized($normalized)) {
                continue;
            }
            $urls[$normalized] = true;
        }

        return array_keys($urls);
    }

    /**
     * @param array<string, string> $regionMap
     * @return array<int, array{region_a: string, region_b: string, label: string, label_a: string, label_b: string}>
     */
    protected function buildRegionPairs(array $regionMap): array
    {
        $keys = array_keys($regionMap);
        $pairs = [];
        $n = count($keys);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $keyA = $keys[$i];
                $keyB = $keys[$j];
                $labelA = $regionMap[$keyA];
                $labelB = $regionMap[$keyB];
                $pairs[] = [
                    'region_a' => $keyA,
                    'region_b' => $keyB,
                    'label_a' => $labelA,
                    'label_b' => $labelB,
                    'label' => $this->pairColumnLabel($labelA, $labelB),
                ];
            }
        }

        return $pairs;
    }

    protected function pairColumnLabel(string $labelA, string $labelB): string
    {
        $cityA = $this->cityNameFromTabLabel($labelA);
        $cityB = $this->cityNameFromTabLabel($labelB);

        return $cityA . ' ↔ ' . $cityB;
    }

    /** «Яндекс · Москва» → «Москва» */
    protected function cityNameFromTabLabel(string $tabLabel): string
    {
        $parts = preg_split('/\s*·\s*/u', trim($tabLabel), 2);

        return trim($parts[1] ?? $tabLabel);
    }

    /**
     * @param array<string, array<int, string>> $urlSetsByRegion
     * @param array<int, array{region_a: string, region_b: string}> $regionPairs
     * @return array<int, array<string, mixed>>
     */
    protected function pairwiseDetailsForPhrase(array $urlSetsByRegion, array $regionPairs, int $maxSharedUrls): array
    {
        $out = [];
        foreach ($regionPairs as $pair) {
            $keyA = $pair['region_a'];
            $keyB = $pair['region_b'];
            $setA = $urlSetsByRegion[$keyA] ?? [];
            $setB = $urlSetsByRegion[$keyB] ?? [];
            $detail = $this->pairwiseOverlapDetail($setA, $setB, $maxSharedUrls);

            $out[] = array_merge($pair, $detail);
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $pairDetails
     */
    protected function avgFromPairDetails(array $pairDetails): ?float
    {
        $scores = [];
        foreach ($pairDetails as $detail) {
            if (! isset($detail['overlap']) || $detail['overlap'] === null) {
                continue;
            }
            $scores[] = (float) $detail['overlap'];
        }

        if (count($scores) === 0) {
            return null;
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * Средняя доля общих URL в топе каждого региона: (|∩|/|A| + |∩|/|B|) / 2.
     *
     * @param array<int, string> $setA
     * @param array<int, string> $setB
     * @return array<string, mixed>
     */
    protected function pairwiseOverlapDetail(array $setA, array $setB, int $maxSharedUrls): array
    {
        $countA = count($setA);
        $countB = count($setB);

        if ($countA === 0 && $countB === 0) {
            return [
                'overlap' => null,
                'overlap_pct' => null,
                'overlap_pct_a' => null,
                'overlap_pct_b' => null,
                'shared_count' => 0,
                'shared_urls' => [],
                'count_a' => 0,
                'count_b' => 0,
            ];
        }

        if ($countA === 0 || $countB === 0) {
            return [
                'overlap' => 0.0,
                'overlap_pct' => 0,
                'overlap_pct_a' => 0,
                'overlap_pct_b' => 0,
                'shared_count' => 0,
                'shared_urls' => [],
                'count_a' => $countA,
                'count_b' => $countB,
            ];
        }

        $shared = array_values(array_intersect($setA, $setB));
        $sharedCount = count($shared);
        $pctA = (int) round(100 * $sharedCount / max(1, $countA));
        $pctB = (int) round(100 * $sharedCount / max(1, $countB));
        $overlap = ($sharedCount / $countA + $sharedCount / $countB) / 2;

        return [
            'overlap' => round($overlap, 3),
            'overlap_pct' => (int) round($overlap * 100),
            'overlap_pct_a' => $pctA,
            'overlap_pct_b' => $pctB,
            'shared_count' => $sharedCount,
            'shared_urls' => array_slice($shared, 0, max(1, $maxSharedUrls)),
            'count_a' => $countA,
            'count_b' => $countB,
        ];
    }

    protected function classifyPhrase(float $overlap, float $independentMin, float $dependentMax): string
    {
        if ($overlap >= $independentMin) {
            return 'geo_independent';
        }
        if ($overlap <= $dependentMax) {
            return 'geo_dependent';
        }

        return 'partial';
    }

    /**
     * @param array{geo_independent: int, geo_dependent: int, partial: int} $counts
     */
    protected function overallVerdict(array $counts, int $totalPhrases): string
    {
        if ($totalPhrases === 0) {
            return 'mixed';
        }

        if ($counts['geo_dependent'] === $totalPhrases) {
            return 'geo_dependent';
        }
        if ($counts['geo_independent'] === $totalPhrases) {
            return 'geo_independent';
        }
        if ($counts['geo_dependent'] > $counts['geo_independent'] && $counts['geo_dependent'] >= (int) ceil($totalPhrases * 0.6)) {
            return 'geo_dependent';
        }
        if ($counts['geo_independent'] > $counts['geo_dependent'] && $counts['geo_independent'] >= (int) ceil($totalPhrases * 0.6)) {
            return 'geo_independent';
        }

        return 'mixed';
    }

    protected function normalizeSerpUrl(string $url): string
    {
        return CompetitorSerpDomainFilter::normalizeSerpUrl($url);
    }
}
