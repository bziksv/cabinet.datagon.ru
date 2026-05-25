<?php

namespace App\Services\Competitor;

use App\CompetitorConfig;

/**
 * Группы запросов по схожей выдаче (пересечение URL) + рекомендации по мета-тегам.
 */
class CompetitorMetaRecommendations
{
    /** @var array<int, string> */
    protected $defaultTags = ['title', 'h1', 'description'];

    /**
     * @param array<string, array<string, mixed>> $analysedSites phrase => url => site
     * @param array<string, array<string, array<string, int>>> $totalMetaTags phrase => tag => word => count
     * @param array<int, string>|null $tags
     */
    public function build(array $analysedSites, array $totalMetaTags, $topCount = '10', ?array $tags = null): array
    {
        $tags = $tags !== null && count($tags) > 0 ? $tags : $this->defaultTags;
        $phrases = array_values(array_filter(array_keys($analysedSites), function ($p) {
            return is_string($p) && $p !== '';
        }));

        if (count($phrases) === 0) {
            return ['clusters' => []];
        }

        $urlSets = $this->phraseUrlSets($analysedSites, $phrases);
        $clusters = $this->clusterPhrases($phrases, $urlSets);
        $result = [];
        foreach ($clusters as $index => $phraseGroup) {
            $sharedUrls = $this->sharedUrls($phraseGroup, $urlSets);
            $similarity = $this->clusterSimilarity($phraseGroup, $urlSets);

            $result[] = [
                'id' => $index + 1,
                'phrases' => $phraseGroup,
                'phrase_count' => count($phraseGroup),
                'shared_url_count' => count($sharedUrls),
                'shared_urls' => array_slice($sharedUrls, 0, 12),
                'similarity' => round($similarity, 2),
                'recommendations' => $this->recommendationsForPhrases(
                    $phraseGroup,
                    $totalMetaTags,
                    $tags,
                    $topCount,
                    $analysedSites
                ),
            ];
        }

        usort($result, function ($a, $b) {
            return ($b['phrase_count'] <=> $a['phrase_count'])
                ?: ($b['shared_url_count'] <=> $a['shared_url_count']);
        });

        return ['clusters' => array_values($result)];
    }

    /**
     * @param array<string, array<string, mixed>> $analysedSites
     * @param array<int, string> $phrases
     * @return array<string, array<int, string>>
     */
    protected function phraseUrlSets(array $analysedSites, array $phrases): array
    {
        $sets = [];
        foreach ($phrases as $phrase) {
            $urls = [];
            $sites = $analysedSites[$phrase] ?? [];
            if (! is_array($sites)) {
                $sets[$phrase] = [];

                continue;
            }
            foreach (array_keys($sites) as $url) {
                if (! is_string($url) || $url === '') {
                    continue;
                }
                $normalized = $this->normalizeSerpUrl($url);
                if ($normalized !== '') {
                    $urls[$normalized] = true;
                }
            }
            $sets[$phrase] = array_keys($urls);
        }

        return $sets;
    }

    /**
     * @param array<int, string> $phrases
     * @param array<string, array<int, string>> $urlSets
     * @return array<int, array<int, string>>
     */
    protected function clusterPhrases(array $phrases, array $urlSets): array
    {
        $minJaccard = (float) config('cabinet-competitor-analysis.recommendation_min_jaccard', 0.35);
        $minShared = (int) config('cabinet-competitor-analysis.recommendation_min_shared_urls', 3);

        $parent = [];
        foreach ($phrases as $p) {
            $parent[$p] = $p;
        }

        $find = function ($x) use (&$parent, &$find) {
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };

        $union = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        $n = count($phrases);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $phrases[$i];
                $b = $phrases[$j];
                $setA = $urlSets[$a] ?? [];
                $setB = $urlSets[$b] ?? [];
                if (count($setA) === 0 || count($setB) === 0) {
                    continue;
                }

                $shared = count(array_intersect($setA, $setB));
                $jaccard = $shared / max(1, count(array_unique(array_merge($setA, $setB))));

                if ($shared >= $minShared || $jaccard >= $minJaccard) {
                    $union($a, $b);
                }
            }
        }

        $groups = [];
        foreach ($phrases as $p) {
            $root = $find($p);
            $groups[$root][] = $p;
        }

        return array_values($groups);
    }

    /**
     * @param array<int, string> $phraseGroup
     * @param array<string, array<int, string>> $urlSets
     * @return array<int, string>
     */
    protected function sharedUrls(array $phraseGroup, array $urlSets): array
    {
        if (count($phraseGroup) === 0) {
            return [];
        }

        $intersection = null;
        foreach ($phraseGroup as $phrase) {
            $set = $urlSets[$phrase] ?? [];
            if ($intersection === null) {
                $intersection = $set;

                continue;
            }
            $intersection = array_values(array_intersect($intersection, $set));
        }

        return $intersection ?? [];
    }

    /**
     * @param array<int, string> $phraseGroup
     * @param array<string, array<int, string>> $urlSets
     */
    protected function clusterSimilarity(array $phraseGroup, array $urlSets): float
    {
        if (count($phraseGroup) < 2) {
            return 1.0;
        }

        $scores = [];
        $n = count($phraseGroup);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $urlSets[$phraseGroup[$i]] ?? [];
                $b = $urlSets[$phraseGroup[$j]] ?? [];
                if (count($a) === 0 || count($b) === 0) {
                    continue;
                }
                $shared = count(array_intersect($a, $b));
                $scores[] = $shared / max(1, count(array_unique(array_merge($a, $b))));
            }
        }

        if (count($scores) === 0) {
            return 0.0;
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * @param array<int, string> $phraseGroup
     * @param array<string, array<string, array<string, int>>> $totalMetaTags
     * @param array<int, string> $tags
     * @param array<string, array<string, mixed>> $analysedSites
     */
    protected function recommendationsForPhrases(
        array $phraseGroup,
        array $totalMetaTags,
        array $tags,
        $topCount,
        array $analysedSites
    ): array {
        $groupSize = max(1, count($phraseGroup));
        $competitorPages = $this->countCompetitorPages($phraseGroup, $analysedSites);
        $requestedTop = (int) $topCount;
        if ($requestedTop !== 10 && $requestedTop !== 20) {
            $requestedTop = 10;
        }
        $effectiveTop = min($requestedTop, max(1, $competitorPages));
        $minimumRepeat = $this->minimumRepeatThreshold((string) $effectiveTop);
        $share = (float) config('cabinet-competitor-analysis.recommendation_min_competitor_share', 0.3);
        if ($groupSize === 1) {
            $share = min($share, 0.2);
        }
        $threshold = max(
            $minimumRepeat,
            (int) ceil($competitorPages * $share)
        );
        $threshold = min($threshold, max($minimumRepeat, $competitorPages));

        $merged = [];
        foreach ($phraseGroup as $phrase) {
            if (! isset($totalMetaTags[$phrase]) || ! is_array($totalMetaTags[$phrase])) {
                continue;
            }
            foreach ($totalMetaTags[$phrase] as $tag => $words) {
                if (! in_array($tag, $tags, true) || ! is_array($words)) {
                    continue;
                }
                foreach ($words as $word => $count) {
                    if (! is_string($word) || $word === '' || $this->isNoiseWord($word)) {
                        continue;
                    }
                    $w = mb_strtolower($word);
                    if (! isset($merged[$tag][$w])) {
                        $merged[$tag][$w] = 0;
                    }
                    $merged[$tag][$w] += (int) $count;
                }
            }
        }

        $phraseTokens = $this->phraseTokens($phraseGroup);
        $out = [];
        foreach ($tags as $tag) {
            if (! isset($merged[$tag])) {
                $out[$tag] = [];

                continue;
            }
            $items = [];
            foreach ($merged[$tag] as $word => $count) {
                if ($count < $threshold) {
                    continue;
                }
                $items[] = [
                    'word' => $word,
                    'score' => $count,
                    'in_phrases' => $this->wordInPhraseTokens($word, $phraseTokens),
                    'label' => $this->wordLabel($count, $competitorPages, $groupSize),
                ];
            }

            usort($items, function ($a, $b) {
                if ($a['in_phrases'] !== $b['in_phrases']) {
                    return $b['in_phrases'] <=> $a['in_phrases'];
                }

                return $b['score'] <=> $a['score'];
            });

            $out[$tag] = array_slice($items, 0, (int) config('cabinet-competitor-analysis.recommendation_words_per_tag', 20));
        }

        return $out;
    }

    /**
     * @param array<int, string> $phraseGroup
     */
    protected function countCompetitorPages(array $phraseGroup, array $analysedSites): int
    {
        $max = 0;
        foreach ($phraseGroup as $phrase) {
            $sites = $analysedSites[$phrase] ?? [];
            if (is_array($sites)) {
                $max = max($max, count($sites));
            }
        }

        return max(1, $max);
    }

    /**
     * @param array<int, string> $phraseGroup
     * @return array<int, string>
     */
    protected function phraseTokens(array $phraseGroup): array
    {
        $tokens = [];
        foreach ($phraseGroup as $phrase) {
            foreach (preg_split('/\s+/u', mb_strtolower($phrase), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                if (mb_strlen($part) >= 2) {
                    $tokens[$part] = true;
                }
            }
        }

        return array_keys($tokens);
    }

    protected function wordInPhraseTokens(string $word, array $phraseTokens): bool
    {
        return in_array(mb_strtolower($word), $phraseTokens, true);
    }

    protected function wordLabel(int $count, int $competitorPages, int $groupSize): string
    {
        $perPhrase = round($count / max(1, $groupSize), 1);

        return sprintf(
            'суммарно %d (≈%s на запрос, топ-%d)',
            $count,
            $perPhrase,
            $competitorPages
        );
    }

    protected function isNoiseWord(string $word): bool
    {
        $w = mb_strtolower(trim($word));
        if ($w === '' || mb_strlen($w) < 2) {
            return true;
        }

        $noise = [
            'sorry', 'your', 'request', 'has', 'been', 'denied',
            'the', 'and', 'for', 'with', 'this', 'that', 'from',
        ];

        return in_array($w, $noise, true);
    }

    protected function normalizeSerpUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $host . $path;
    }

    /**
     * @param int|string $topCount
     */
    protected function minimumRepeatThreshold($topCount): int
    {
        $config = CompetitorConfig::first();
        if ($config === null) {
            return 3;
        }

        if ((string) $topCount === '20') {
            return (int) $config->count_repeat_top_20;
        }

        return (int) $config->count_repeat_top_10;
    }
}
