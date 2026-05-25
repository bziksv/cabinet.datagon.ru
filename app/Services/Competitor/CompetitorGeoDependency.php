<?php

namespace App\Services\Competitor;

use App\Support\CompetitorSearchRegions;
use App\Support\CompetitorSerpDomainFilter;

/**
 * Сравнение выдачи между регионами: геозависимость запросов.
 */
class CompetitorGeoDependency
{
    /**
     * @param array<string, array<string, mixed>> $byRegion
     * @param array<int, array{key?: string, engine?: string, id?: string, tabLabel?: string, name?: string}> $regionsMeta
     */
    public function analyze(array $byRegion, array $regionsMeta): ?array
    {
        $regionMap = $this->resolveRegionMap($regionsMeta, $byRegion);
        if (count($regionMap) < 2) {
            return null;
        }

        $phrases = $this->collectPhrases($byRegion, $regionMap);
        if (count($phrases) === 0) {
            return null;
        }

        $independentMin = (float) config('cabinet-competitor-analysis.geo_independent_min_jaccard', 0.65);
        $dependentMax = (float) config('cabinet-competitor-analysis.geo_dependent_max_jaccard', 0.4);

        $phraseRows = [];
        $jaccardForAvg = [];
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

            $jaccard = $this->avgPairwiseJaccard($urlSetsByRegion);
            if ($jaccard === null) {
                $counts['skipped']++;
                $phraseRows[] = [
                    'phrase' => $phrase,
                    'status' => 'skipped',
                    'jaccard' => null,
                    'jaccard_pct' => null,
                    'urls_by_region' => array_map(static function (array $urls) {
                        return count($urls);
                    }, $urlSetsByRegion),
                ];

                continue;
            }

            $status = $this->classifyPhrase($jaccard, $independentMin, $dependentMax);
            $counts[$status]++;
            $jaccardForAvg[] = $jaccard;

            $phraseRows[] = [
                'phrase' => $phrase,
                'status' => $status,
                'jaccard' => round($jaccard, 3),
                'jaccard_pct' => (int) round($jaccard * 100),
                'urls_by_region' => array_map(static function (array $urls) {
                    return count($urls);
                }, $urlSetsByRegion),
            ];
        }

        usort($phraseRows, function ($a, $b) {
            $aj = $a['jaccard'] ?? -1;
            $bj = $b['jaccard'] ?? -1;

            return $aj <=> $bj;
        });

        $scoredPhraseCount = count($jaccardForAvg);
        $verdict = $this->overallVerdict($counts, $scoredPhraseCount);
        $avgJaccard = $scoredPhraseCount > 0
            ? array_sum($jaccardForAvg) / $scoredPhraseCount
            : 0.0;

        return [
            'verdict' => $verdict,
            'avg_jaccard' => round($avgJaccard, 3),
            'avg_jaccard_pct' => (int) round($avgJaccard * 100),
            'excludes_aggregators' => true,
            'region_count' => count($regionMap),
            'phrase_count' => count($phraseRows),
            'counts' => $counts,
            'regions' => array_values(array_map(static function ($key, $label) {
                return ['key' => $key, 'label' => $label];
            }, array_keys($regionMap), $regionMap)),
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
     * @param array<string, array<int, string>> $urlSetsByRegion
     */
    protected function avgPairwiseJaccard(array $urlSetsByRegion): ?float
    {
        $keys = array_keys($urlSetsByRegion);
        $n = count($keys);
        if ($n < 2) {
            return 1.0;
        }

        $scores = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $urlSetsByRegion[$keys[$i]] ?? [];
                $b = $urlSetsByRegion[$keys[$j]] ?? [];
                if (count($a) === 0 && count($b) === 0) {
                    continue;
                }
                if (count($a) === 0 || count($b) === 0) {
                    $scores[] = 0.0;

                    continue;
                }
                $shared = count(array_intersect($a, $b));
                $scores[] = $shared / max(1, count(array_unique(array_merge($a, $b))));
            }
        }

        if (count($scores) === 0) {
            return null;
        }

        return array_sum($scores) / count($scores);
    }

    protected function classifyPhrase(float $jaccard, float $independentMin, float $dependentMax): string
    {
        if ($jaccard >= $independentMin) {
            return 'geo_independent';
        }
        if ($jaccard <= $dependentMax) {
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
