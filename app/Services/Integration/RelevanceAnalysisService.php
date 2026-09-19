<?php

namespace App\Services\Integration;

use App\Http\Controllers\RelevanceController;
use App\IntegrationAnalysis;
use App\IntegrationApiKey;
use App\Jobs\Relevance\RelevanceAnalyseQueue;
use App\Relevance;
use App\RelevanceHistory;
use App\RelevanceHistoryResult;
use App\RelevanceProgress;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RelevanceAnalysisService
{
    /**
     * @param array{url:string,phrase:string,region?:string,engine?:string,top?:int} $input
     */
    public function start(User $user, ?IntegrationApiKey $apiKey, array $input): IntegrationAnalysis
    {
        $url = trim((string) ($input['url'] ?? ''));
        $phrase = trim((string) ($input['phrase'] ?? ''));

        if ($url === '') {
            throw new InvalidArgumentException('url is required');
        }
        if ($phrase === '' || mb_strlen($phrase) > 50) {
            throw new InvalidArgumentException('phrase is required (max 50 chars)');
        }

        $parsed = parse_url($url);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new InvalidArgumentException('url must be a full absolute URL');
        }

        $engine = strtolower((string) ($input['engine'] ?? $input['searchEngine'] ?? config('integration_api.default_engine', 'yandex')));
        if (!in_array($engine, ['yandex', 'google'], true)) {
            $engine = 'yandex';
        }

        $region = (string) ($input['region'] ?? config('integration_api.default_region', '213'));
        $allowedRegions = config('integration_api.allowed_regions');
        if (is_array($allowedRegions) && $allowedRegions !== [] && !in_array($region, $allowedRegions, true)) {
            throw new InvalidArgumentException('region is not allowed');
        }
        // Яндекс lr — обычно цифры; Google — код страны
        if ($engine === 'yandex' && $region !== '' && !ctype_digit($region)) {
            throw new InvalidArgumentException('region must be numeric for yandex');
        }
        $top = (int) ($input['top'] ?? $input['count'] ?? config('integration_api.default_top', 20));
        if ($top < 10) {
            $top = 10;
        }
        if ($top > 50) {
            $top = 50;
        }

        Auth::setUser($user);

        $payload = [
            'type' => 'phrase',
            'link' => $url,
            'phrase' => $phrase,
            'region' => $region,
            'searchEngine' => $engine,
            'count' => (string) $top,
            'noIndex' => true,
            'hiddenText' => false,
            'switchMyListWords' => false,
            'conjunctionsPrepositionsPronouns' => true,
            'exp' => false,
            'searchPassages' => false,
            'listWords' => '',
            'ignoredDomains' => '',
            'siteList' => '',
            'separator' => "\n",
            'pageHash' => 'api_' . Str::random(16),
        ];

        $cost = RelevanceHistory::serpRequestCost($payload);
        if (RelevanceHistory::checkRelevanceAnalysisLimits($cost)) {
            throw new InvalidArgumentException('relevance_limit_exhausted');
        }

        $progress = new RelevanceProgress([
            'user_id' => $user->id,
            'hash' => md5($user->id . microtime(true) . Str::random(8)),
            'progress' => 0,
        ]);
        $progress->save();

        $payload['hash'] = $progress->hash;

        $analysis = IntegrationAnalysis::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'api_key_id' => $apiKey ? $apiKey->id : null,
            'progress_hash' => $progress->hash,
            'status' => IntegrationAnalysis::STATUS_QUEUED,
            'url' => $url,
            'phrase' => $phrase,
            'request_payload' => $payload,
        ]);

        RelevanceAnalyseQueue::dispatch($payload, false, $user->id, 'full')
            ->onQueue(RelevanceController::HIGH_QUEUE)
            ->onConnection('database');

        \App\Support\RelevanceLocalQueueGuard::ensureWorkers();

        return $analysis;
    }

    public function refreshStatus(IntegrationAnalysis $analysis): IntegrationAnalysis
    {
        if (in_array($analysis->status, [IntegrationAnalysis::STATUS_DONE, IntegrationAnalysis::STATUS_FAILED], true)) {
            return $analysis;
        }

        $progress = RelevanceProgress::where('hash', $analysis->progress_hash)->first();

        if (!$progress) {
            if ($this->attachHistoryIfReady($analysis)) {
                return $analysis;
            }

            // Progress row may be deleted after client endProgress; try history by recent match
            $this->attachHistoryByRequestMatch($analysis);
            if ($analysis->history_id) {
                $analysis->status = IntegrationAnalysis::STATUS_DONE;
                $analysis->save();
            }

            return $analysis;
        }

        if ($progress->error) {
            $analysis->status = IntegrationAnalysis::STATUS_FAILED;
            $raw = trim((string) $progress->error);
            // relevance_progress.error — tinyint-флаг (1), не текст исключения.
            if ($raw === '' || $raw === '1' || strtolower($raw) === 'true') {
                if ($analysis->error && !in_array($analysis->error, ['1', 'analysis_failed'], true)) {
                    $raw = (string) $analysis->error;
                } else {
                    $raw = 'analysis_save_failed';
                }
            }
            $analysis->error = mb_substr($raw, 0, 1000);
            $analysis->save();
            return $analysis;
        }

        $pct = (int) $progress->progress;
        if ($pct >= 100) {
            if ($this->attachHistoryIfReady($analysis)) {
                return $analysis;
            }
            // 100% уже есть, результат ещё пишется — остаёмся running, но пробуем url/phrase
            $this->attachHistoryByRequestMatch($analysis);
            if ($analysis->history_id) {
                $analysis->status = IntegrationAnalysis::STATUS_DONE;
                $analysis->save();
            } else {
                $analysis->status = IntegrationAnalysis::STATUS_RUNNING;
                $analysis->save();
            }
        } elseif ($pct > 0) {
            if ($analysis->status !== IntegrationAnalysis::STATUS_RUNNING) {
                $analysis->status = IntegrationAnalysis::STATUS_RUNNING;
                $analysis->save();
            }
        }

        return $analysis;
    }

    /**
     * Привязать history_id по hash результата скана.
     */
    protected function attachHistoryIfReady(IntegrationAnalysis $analysis): bool
    {
        $result = RelevanceHistoryResult::where('hash', $analysis->progress_hash)->first();
        if ($result && $result->project_id) {
            $history = RelevanceHistory::where('id', (int) $result->project_id)
                ->where('user_id', $analysis->user_id)
                ->first();
            if (!$history) {
                return false;
            }
            $analysis->status = IntegrationAnalysis::STATUS_DONE;
            $analysis->history_id = (int) $history->id;
            $analysis->save();
            return true;
        }
        return false;
    }

    /**
     * Fallback: последняя история того же user/url/phrase (если hash ещё не записан).
     * Берём только строки, у которых уже есть relevance_history_result —
     * иначе API отдаст балл без TLP/облаков (гонка: progress=100 до saveHistoryResult).
     */
    protected function attachHistoryByRequestMatch(IntegrationAnalysis $analysis): void
    {
        if ($analysis->history_id) {
            return;
        }
        $query = RelevanceHistory::where('user_id', $analysis->user_id)
            ->where('main_link', $analysis->url)
            ->where('phrase', $analysis->phrase)
            ->orderByDesc('id');

        if ($analysis->created_at) {
            $query->where('created_at', '>=', $analysis->created_at->copy()->subMinutes(5));
        } else {
            $query->where('created_at', '>=', now()->subHour());
        }

        $candidates = $query->limit(5)->get(['id']);
        foreach ($candidates as $history) {
            $hid = (int) $history->id;
            if ($hid <= 0) {
                continue;
            }
            if (RelevanceHistoryResult::where('project_id', $hid)->exists()) {
                $analysis->history_id = $hid;
                return;
            }
        }
    }

    public function progressPercent(IntegrationAnalysis $analysis): int
    {
        if ($analysis->status === IntegrationAnalysis::STATUS_DONE) {
            return 100;
        }
        if ($analysis->status === IntegrationAnalysis::STATUS_FAILED) {
            return 0;
        }

        $progress = RelevanceProgress::where('hash', $analysis->progress_hash)->first();
        return $progress ? (int) $progress->progress : 0;
    }

    /**
     * Последние проверки посадочной для магазина (динамика баллов).
     *
     * Важно: без leading-wildcard LIKE на remote MySQL (десятки секунд).
     * Сначала точные URL-варианты, потом суффикс path.
     *
     * @return array<int, array{history_id:int,phrase:string,url:string,points:mixed,points_ideal:?int,coverage:mixed,density:mixed,position:mixed,engine:string,region:string,top:?int,last_check:mixed,created_at:?string,delta_points:?float}>
     */
    public function listHistoriesForLanding(User $user, ?string $url, ?string $phrase, int $limit = 10, ?string $siteHost = null): array
    {
        $rows = collect();

        $select = [
            'id', 'phrase', 'main_link', 'points', 'coverage', 'density',
            'position', 'last_check', 'created_at', 'user_id', 'region', 'request',
        ];

        if ($url) {
            $variants = $this->urlMatchVariants($url);
            $allowedHosts = $this->allowedHostsForLanding($url, $siteHost);
            $byId = [];

            $exact = RelevanceHistory::where('user_id', $user->id)
                ->whereIn('main_link', $variants)
                ->orderByDesc('id')
                ->limit($limit)
                ->get($select);
            foreach ($exact as $h) {
                if ($this->historyMatchesLanding($h, $url, $allowedHosts)) {
                    $byId[(int) $h->id] = $h;
                }
            }

            // Тот же path только на разрешённых хостах (локал ↔ свой site_url), не «любой домен»
            $path = $this->normalizedPath($url);
            if ($path !== '' && $path !== '/' && count($byId) < $limit && $allowedHosts !== []) {
                $suffixes = [$path, $path . '/'];
                $byPath = RelevanceHistory::where('user_id', $user->id)
                    ->where(function ($q) use ($suffixes) {
                        foreach ($suffixes as $i => $sfx) {
                            $like = '%' . $sfx;
                            if ($i === 0) {
                                $q->where('main_link', 'like', $like);
                            } else {
                                $q->orWhere('main_link', 'like', $like);
                            }
                        }
                    })
                    ->orderByDesc('id')
                    ->limit($limit * 5)
                    ->get($select);
                foreach ($byPath as $h) {
                    $id = (int) $h->id;
                    if (isset($byId[$id])) {
                        continue;
                    }
                    if (!$this->historyMatchesLanding($h, $url, $allowedHosts)) {
                        continue;
                    }
                    $byId[$id] = $h;
                    if (count($byId) >= $limit) {
                        break;
                    }
                }
            }

            $rows = collect(array_values($byId))->sortByDesc('id')->values();
            if ($rows->count() > $limit) {
                $rows = $rows->take($limit)->values();
            }
        } elseif ($phrase) {
            $rows = RelevanceHistory::where('user_id', $user->id)
                ->where('phrase', $phrase)
                ->orderByDesc('id')
                ->limit($limit)
                ->get($select);
        }

        // Узкий фильтр по фразе уже после URL-выборки (без тяжёлого AND на remote)
        if ($rows->isNotEmpty() && $phrase) {
            $filtered = $rows->filter(static function ($h) use ($phrase) {
                return (string) $h->phrase === $phrase;
            })->values();
            if ($filtered->isNotEmpty()) {
                $rows = $filtered;
            }
        }

        // Fallback: integration_analyses на локальной БД (быстро), потом точечный whereIn id
        if ($rows->isEmpty() && $url) {
            $variants = $this->urlMatchVariants($url);
            $allowedHosts = $this->allowedHostsForLanding($url, $siteHost);
            $ids = IntegrationAnalysis::where('user_id', $user->id)
                ->where('status', IntegrationAnalysis::STATUS_DONE)
                ->whereNotNull('history_id')
                ->whereIn('url', $variants)
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('history_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($ids !== []) {
                $candidates = RelevanceHistory::where('user_id', $user->id)
                    ->whereIn('id', $ids)
                    ->orderByDesc('id')
                    ->limit($limit * 2)
                    ->get($select);
                $rows = $candidates->filter(function ($h) use ($url, $allowedHosts) {
                    return $this->historyMatchesLanding($h, $url, $allowedHosts);
                })->take($limit)->values();
            }
        }

        $items = [];
        $prevPoints = null;
        $chrono = $rows->sortBy('id')->values();
        $idealByHistoryId = $this->idealPointsByHistoryIds(
            $chrono->pluck('id')->map(static function ($id) {
                return (int) $id;
            })->all()
        );
        foreach ($chrono as $history) {
            $points = $history->points;
            $delta = null;
            if ($prevPoints !== null && is_numeric($points) && is_numeric($prevPoints)) {
                $delta = round((float) $points - (float) $prevPoints, 2);
            }
            if (is_numeric($points)) {
                $prevPoints = $points;
            }
            $hid = (int) $history->id;
            $params = $this->analysisParamsFromHistory($history);
            $items[] = [
                'history_id' => $hid,
                'phrase' => (string) $history->phrase,
                'url' => (string) $history->main_link,
                'points' => $history->points,
                'points_ideal' => $idealByHistoryId[$hid] ?? null,
                'coverage' => $history->coverage,
                'density' => $history->density,
                'position' => $history->position,
                'engine' => $params['engine'],
                'region' => $params['region'],
                'top' => $params['top'],
                'last_check' => $history->last_check,
                'created_at' => optional($history->created_at)->toIso8601String(),
                'delta_points' => $delta,
            ];
        }

        return array_reverse($items);
    }

    /**
     * ПС / регион / ТОП из строки истории (как в запуске анализа).
     *
     * @return array{engine:string,region:string,top:?int}
     */
    public function analysisParamsFromHistory($history): array
    {
        $request = is_object($history) ? ($history->request ?? null) : null;
        if (is_string($request)) {
            $decoded = json_decode($request, true);
            $request = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($request)) {
            $request = [];
        }

        $engine = strtolower((string) ($request['searchEngine'] ?? $request['engine'] ?? 'yandex'));
        if (!in_array($engine, ['yandex', 'google'], true)) {
            $engine = 'yandex';
        }

        $region = trim((string) (
            (is_object($history) ? ($history->region ?? '') : '')
            ?: ($request['region'] ?? '')
        ));

        $top = (int) ($request['count'] ?? $request['top'] ?? 0);
        if ($top < 1) {
            $top = null;
        }

        return [
            'engine' => $engine,
            'region' => $region,
            'top' => $top,
        ];
    }

    /**
     * Рекомендуемые баллы из average_values (как «ваш / рек.» в анализаторе).
     *
     * @param  int[]  $historyIds
     * @return array<int, int>
     */
    public function idealPointsByHistoryIds(array $historyIds): array
    {
        $historyIds = array_values(array_unique(array_filter(array_map('intval', $historyIds))));
        if ($historyIds === []) {
            return [];
        }

        $out = [];
        $rows = RelevanceHistoryResult::whereIn('project_id', $historyIds)
            ->get(['project_id', 'average_values']);
        foreach ($rows as $row) {
            $ideal = $this->idealPointsFromAverageValues($row->average_values);
            if ($ideal !== null) {
                $out[(int) $row->project_id] = $ideal;
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $averageValues
     */
    public function idealPointsFromAverageValues($averageValues): ?int
    {
        if ($averageValues === null || $averageValues === '') {
            return null;
        }
        if (is_string($averageValues)) {
            $averageValues = json_decode($averageValues, true);
        }
        if (!is_array($averageValues) || !isset($averageValues['points']) || !is_numeric($averageValues['points'])) {
            return null;
        }

        return (int) round((float) $averageValues['points']);
    }

    /**
     * @return string[]
     */
    protected function urlMatchVariants(string $url): array
    {
        $url = trim($url);
        $out = [$url];
        if (strpos($url, 'https://') === 0) {
            $out[] = 'http://' . substr($url, 8);
        } elseif (strpos($url, 'http://') === 0) {
            $out[] = 'https://' . substr($url, 7);
        }
        $trimmed = rtrim($url, '/');
        if ($trimmed !== $url) {
            $out[] = $trimmed;
        } else {
            $out[] = $url . '/';
        }

        // Локальный хост ↔ прод: в истории часто лежит канонический URL
        $parts = parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $path = $path === '' ? '/' : $path;
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        $hosts = [];
        if (!empty($parts['host'])) {
            $hosts[] = (string) $parts['host'];
        }
        foreach ($hosts as $host) {
            foreach (['https', 'http'] as $scheme) {
                $out[] = $scheme . '://' . $host . $path . $query;
                $out[] = $scheme . '://' . $host . rtrim($path, '/') . $query;
                if (substr($path, -1) !== '/') {
                    $out[] = $scheme . '://' . $host . $path . '/' . $query;
                }
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    protected function normalizedPath(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    /**
     * Хосты, с которых можно подтягивать историю для этой посадочной.
     * Без site_host при локальном URL — только сам localhost (не чужие домены по path).
     *
     * @return string[] lowercase hosts
     */
    protected function allowedHostsForLanding(string $url, ?string $siteHost = null): array
    {
        $hosts = [];
        $reqHost = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($reqHost !== '') {
            $hosts[$reqHost] = true;
        }
        $siteHost = strtolower(trim((string) $siteHost));
        if ($siteHost !== '') {
            $hosts[$siteHost] = true;
            if (strpos($siteHost, 'www.') === 0) {
                $hosts[substr($siteHost, 4)] = true;
            } else {
                $hosts['www.' . $siteHost] = true;
            }
        }
        // Локал ↔ 127.0.0.1 взаимозаменяемы
        if (isset($hosts['localhost']) || isset($hosts['127.0.0.1'])) {
            $hosts['localhost'] = true;
            $hosts['127.0.0.1'] = true;
        }
        // Если запрос с локала и передан site_host магазина — можно матчить прод
        // Если site_host не передан — чужие домены по path НЕ берём
        return array_keys($hosts);
    }

    /**
     * История подходит к посадочной: тот же path + host из allowlist.
     */
    protected function historyMatchesLanding($history, string $requestUrl, array $allowedHosts): bool
    {
        $main = trim((string) ($history->main_link ?? ''));
        if ($main === '') {
            return false;
        }
        $reqPath = $this->normalizedPath($requestUrl);
        $histPath = $this->normalizedPath($main);
        if ($reqPath === '' || $reqPath === '/' || $histPath !== $reqPath) {
            return false;
        }
        $histHost = strtolower((string) (parse_url($main, PHP_URL_HOST) ?: ''));
        if ($histHost === '') {
            return false;
        }
        if ($allowedHosts === []) {
            return false;
        }
        return in_array($histHost, $allowedHosts, true);
    }

    /**
     * TLP (unigram) для генерации: сортировка по TF-IDF ТОП.
     * Сначала полностью отсутствующие на посадочной, потом с разницей.
     *
     * @return array{
     *   missing: array<int, array{word:string,avg_competitors:float,on_landing:float,suggested_count:int,tfidf_top:float,tfidf_site:float,bucket:string}>,
     *   diff: array<int, array{word:string,avg_competitors:float,on_landing:float,suggested_count:int,tfidf_top:float,tfidf_site:float,bucket:string}>,
     *   missing_total: int,
     *   diff_total: int
     * }
     */
    public function tlpForGeneration(RelevanceHistory $history): array
    {
        // Как /show-history: hybrid TF-IDF + подпись группы = max surface form.
        // Сырой unigram_table из БД даёт другие цифры/порядок (исследования вместо кп).
        $unigramRaw = $this->enrichedUnigramForHistory($history);

        $missing = [];
        $diff = [];

        foreach ($unigramRaw as $word => $item) {
            if ($word === '' || !is_array($item)) {
                continue;
            }
            $totalBlock = is_array($item['total'] ?? null) ? $item['total'] : $item;
            $avg = (float) ($totalBlock['avgInTotalCompetitors'] ?? 0);
            $onLanding = (float) ($totalBlock['totalRepeatMainPage'] ?? 0);
            $tfidfTop = (float) ($totalBlock['tfidfTop'] ?? $totalBlock['score'] ?? 0);
            $tfidfSite = (float) ($totalBlock['tfidfSite'] ?? 0);

            // В TLP интересуют слова, важные в ТОПе
            if ($tfidfTop <= 0 && $avg <= 0) {
                continue;
            }

            $row = [
                'word' => (string) $word,
                'avg_competitors' => $avg,
                'on_landing' => $onLanding,
                'suggested_count' => 1,
                'tfidf_top' => $tfidfTop,
                'tfidf_site' => $tfidfSite,
                'bucket' => 'diff',
            ];

            if ($onLanding == 0.0) {
                $row['bucket'] = 'missing';
                $row['suggested_count'] = (int) max(1, (int) ceil($avg > 0 ? $avg : 1));
                $missing[] = $row;
            } elseif ($avg > $onLanding || $tfidfTop > $tfidfSite) {
                $row['bucket'] = 'diff';
                $gap = $avg > $onLanding ? ($avg - $onLanding) : 1;
                $row['suggested_count'] = (int) max(1, (int) ceil($gap));
                $diff[] = $row;
            }
        }

        $sortTfidf = static function (array $a, array $b): int {
            $cmp = $b['tfidf_top'] <=> $a['tfidf_top'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['word'], $b['word']);
        };
        usort($missing, $sortTfidf);
        usort($diff, $sortTfidf);

        $missingCap = max(0, (int) config('integration_api.tlp_missing_limit', 200));
        $diffCap = max(0, (int) config('integration_api.tlp_diff_limit', 5));

        return [
            'missing' => array_slice($missing, 0, $missingCap),
            'diff' => array_slice($diff, 0, $diffCap),
            'missing_total' => count($missing),
            'diff_total' => count($diff),
        ];
    }

    /**
     * @return array{phrases: array, unigram: array}
     */
    public function missingPhrases(RelevanceHistory $history, string $filter = 'zero'): array
    {
        $phrasesRaw = [];
        $unigramRaw = [];

        $columns = ['id', 'project_id', 'phrases', 'unigram_table', 'compressed', 'cleaning'];
        $row = RelevanceHistoryResult::where('project_id', (int) $history->id)
            ->select($columns)
            ->first();

        if ($row) {
            $phrasesRaw = Relevance::uncompressItem($row->phrases) ?: [];
            $unigramRaw = Relevance::uncompressItem($row->unigram_table) ?: [];
            if (!is_array($phrasesRaw)) {
                $phrasesRaw = [];
            }
            if (!is_array($unigramRaw)) {
                $unigramRaw = [];
            }
        }

        return [
            'phrases' => $this->filterWordMap($phrasesRaw, $filter),
            'unigram' => $this->filterWordMap($unigramRaw, $filter),
        ];
    }

    /**
     * TF-IDF облака (конкуренты / посадочная) — те же hybrid-метрики, что в /show-history.
     *
     * @return array{
     *   competitors: array{total: array<int, array{text:string,weight:float}>, text: array<int, array{text:string,weight:float}>, links: array<int, array{text:string,weight:float}>},
     *   landing: array{total: array<int, array{text:string,weight:float}>, text: array<int, array{text:string,weight:float}>, links: array<int, array{text:string,weight:float}>}
     * }
     */
    public function cloudsForHistory(RelevanceHistory $history, int $limit = 100): array
    {
        $limit = max(20, min(200, $limit));
        $unigram = $this->enrichedUnigramForHistory($history);
        $competitors = [];
        $landing = [];
        Relevance::applyHybridTfCloudsFromUnigramToPrepared($unigram, $competitors, $landing);

        return [
            'competitors' => [
                'total' => $this->slimCloudWords($competitors['totalTf'] ?? [], $limit),
                'text' => $this->slimCloudWords($competitors['textTf'] ?? [], $limit),
                'links' => $this->slimCloudWords($competitors['linkTf'] ?? [], $limit),
            ],
            'landing' => [
                'total' => $this->slimCloudWords($landing['totalTf'] ?? [], $limit),
                'text' => $this->slimCloudWords($landing['textTf'] ?? [], $limit),
                'links' => $this->slimCloudWords($landing['linkTf'] ?? [], $limit),
            ],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{text:string,weight:float}>
     */
    protected function slimCloudWords($raw, int $limit): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $key => $item) {
            if ($key === 'count' || !is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $weight = (float) ($item['tfidfScore'] ?? $item['weight'] ?? 0);
            if ($weight <= 0) {
                continue;
            }
            $items[] = [
                'text' => $text,
                'weight' => round($weight, 6),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return $b['weight'] <=> $a['weight'];
        });

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    protected function filterWordMap(array $words, string $filter): array
    {
        $out = [];
        foreach ($words as $word => $item) {
            if ($word === '' || !is_array($item)) {
                continue;
            }

            $totalBlock = is_array($item['total'] ?? null) ? $item['total'] : $item;
            $avg = (float) ($totalBlock['avgInTotalCompetitors'] ?? data_get($item, 'total.avgInTotalCompetitors', 0));
            $total = (float) ($totalBlock['totalRepeatMainPage'] ?? data_get($item, 'total.totalRepeatMainPage', 0));
            $tfidfTop = (float) ($totalBlock['tfidfTop'] ?? $totalBlock['score'] ?? 0);

            $include = false;
            $suggested = 1;

            if ($filter === 'diff') {
                if ($avg > $total) {
                    $include = true;
                    $suggested = (int) max(1, ceil($avg - $total));
                }
            } elseif ($filter === 'all') {
                $include = true;
                $suggested = (int) max(1, ceil($avg > $total ? ($avg - $total) : 1));
            } else {
                // zero — отсутствует на посадочной
                if ($total == 0.0) {
                    $include = true;
                    $suggested = (int) (ceil($avg) > 0 ? ceil($avg) : 1);
                }
            }

            if ($include) {
                $out[] = [
                    'word' => (string) $word,
                    'avg_competitors' => $avg,
                    'on_landing' => $total,
                    'suggested_count' => $suggested,
                    'tfidf_top' => $tfidfTop,
                ];
            }
        }

        usort($out, static function (array $a, array $b): int {
            return ($b['tfidf_top'] ?? 0) <=> ($a['tfidf_top'] ?? 0);
        });

        $max = max(1, (int) config('integration_api.missing_phrases_max', 500));
        return array_slice($out, 0, $max);
    }

    /**
     * Unigram как в кабинете /show-history (tables): hybrid TF-IDF + relabel.
     *
     * @return array<string, mixed>
     */
    protected function enrichedUnigramForHistory(RelevanceHistory $history): array
    {
        // Не трогаем $history->results — lazy/eager тянет longtext sites и валит PHP memory_limit.
        $columns = Relevance::historyResultColumnsForPart('tables');
        if (!is_array($columns) || $columns === []) {
            return [];
        }
        if (!in_array('id', $columns, true)) {
            $columns[] = 'id';
        }

        $row = RelevanceHistoryResult::where('project_id', (int) $history->id)
            ->select($columns)
            ->first();
        if (!$row) {
            return [];
        }

        $data = Relevance::uncompress($row, 'tables');
        $unigram = $data['unigram_table'] ?? [];

        return is_array($unigram) ? $unigram : [];
    }
}
