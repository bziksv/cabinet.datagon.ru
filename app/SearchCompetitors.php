<?php

namespace App;

use App\Classes\Xml\SimplifiedXmlFacade;
use App\Support\CompetitorAnalysisDebugLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchCompetitors extends Model
{
    protected $guarded = [];

    protected $table = 'competitor_analysis_count_checks';

    protected $metaTags = [];

    protected $sites = [];

    protected $analysedSites = [];

    protected $pagesCounter = [];

    protected $totalMetaTags = [];

    protected $domainsPosition = [];

    protected $urls = [];

    /** @var array<int, array{id: string, name: string, text: string, engine?: string, key?: string}> */
    protected $regions = [];

    /** @var array<string, array<string, mixed>> */
    protected $byRegion = [];

    /** @var array<int, string> */
    protected $searchEngines = ['yandex'];

    /**
     * @var array<int, array{engine: string, regions: array<int, array{id: string, name: string, text: string}>}>
     */
    protected $analysisPlan = [];

    protected $region;

    /** @var string yandex|google */
    protected $searchEngine = 'yandex';

    protected $phrases;

    protected $count;

    protected $duplicates = [];

    protected $savedHtml = [];

    protected $countPhrases;

    public $pageHash;

    protected $userId;

    public function getCountPhrases()
    {
        return $this->countPhrases;
    }

    public function setUserId(int $id)
    {
        $this->userId = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setPageHash(string $pageHash)
    {
        $this->pageHash = $pageHash;
    }

    public function setPhrases(string $string)
    {
        $phrases = explode("\n", $string);

        $this->phrases = array_unique(array_diff($phrases, ['']));
    }

    /**
     * @param array<int, array{id: string, name: string, text?: string}> $regions
     */
    public function setSearchEngine(string $searchEngine): void
    {
        $this->searchEngine = \App\Support\CompetitorSearchRegions::normalizeEngine($searchEngine);
        $this->searchEngines = [$this->searchEngine];
    }

    public function getSearchEngine(): string
    {
        return $this->searchEngine;
    }

    /**
     * @param array<int, array{engine: string, regions: array}> $plan
     */
    public function setAnalysisPlan(array $plan): void
    {
        $this->analysisPlan = $plan;
        $this->searchEngines = [];
        $this->regions = \App\Support\CompetitorSearchRegions::flattenRegionsForTabs($plan);

        foreach ($plan as $item) {
            $engine = \App\Support\CompetitorSearchRegions::normalizeEngine($item['engine'] ?? '');
            if (!in_array($engine, $this->searchEngines, true)) {
                $this->searchEngines[] = $engine;
            }
        }

        if (!empty($this->regions)) {
            $this->region = (string) $this->regions[0]['id'];
            $this->searchEngine = (string) ($this->regions[0]['engine'] ?? 'yandex');
        }
    }

    public function setRegions(array $regions)
    {
        $this->regions = $regions;
        if (!empty($regions)) {
            $this->region = (string) $regions[0]['id'];
        }

        $engine = \App\Support\CompetitorSearchRegions::normalizeEngine($this->searchEngine);
        $this->analysisPlan = [
            [
                'engine' => $engine,
                'regions' => $regions,
            ],
        ];
        $this->searchEngines = [$engine];
    }

    public function setRegion(string $region)
    {
        $item = \App\Support\YandexLrRegions::find($region);
        $this->setRegions($item ? [$item] : []);
    }

    public function setCount(int $count)
    {
        $this->count = $count;
    }

    public function getResult()
    {
        $payload = [
            'searchEngine' => $this->searchEngines[0] ?? $this->searchEngine,
            'searchEngines' => $this->searchEngines,
            'regions' => $this->regions,
            'byRegion' => $this->tryConvertEncoding($this->byRegion),
            'analysisCount' => (int) $this->count,
        ];

        $geoDependency = $this->buildGeoDependencyPayload();
        if ($geoDependency !== null) {
            $payload['geoDependency'] = $this->tryConvertEncoding($geoDependency);
        }

        $firstKey = $this->regions[0]['key'] ?? null;
        if ($firstKey === null && isset($this->regions[0]['engine'], $this->regions[0]['id'])) {
            $firstKey = \App\Support\CompetitorSearchRegions::regionKey(
                (string) $this->regions[0]['engine'],
                (string) $this->regions[0]['id']
            );
        }

        if ($firstKey !== null && isset($this->byRegion[$firstKey])) {
            $first = $this->byRegion[$firstKey];
            $payload['analysedSites'] = $this->tryConvertEncoding($first['analysedSites'] ?? []);
            $payload['pagesCounter'] = $this->tryConvertEncoding($first['pagesCounter'] ?? []);
            $payload['totalMetaTags'] = $this->tryConvertEncoding($first['totalMetaTags'] ?? []);
            $payload['domainsPosition'] = $this->tryConvertEncoding($first['domainsPosition'] ?? []);
            $payload['urls'] = $this->tryConvertEncoding($first['urls'] ?? []);
        }

        return json_encode($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildGeoDependencyPayload(): ?array
    {
        if (count($this->regions) < 2 || count($this->byRegion) < 2) {
            return null;
        }

        return (new \App\Services\Competitor\CompetitorGeoDependency())->analyze(
            $this->byRegion,
            $this->regions
        );
    }

    protected function tryConvertEncoding($object)
    {
        try {
            return mb_convert_encoding($object, 'UTF-8', 'auto');
        } catch (Throwable $e) {
            return $object;
        }
    }

    public static function markProgressFailed(string $pageHash, string $message): void
    {
        CompetitorAnalysisDebugLog::error($pageHash, 'progress.failed', ['message' => $message]);
        CompetitorAnalysisDebugLog::rememberTerminal($pageHash, [
            'failed' => true,
            'message' => $message,
        ]);

        CompetitorsProgressBar::where('page_hash', '=', $pageHash)->update([
            'percent' => 100,
            'result' => json_encode([
                'error' => true,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    protected function updateProgressPercent(int $percent, ?string $stage = null): void
    {
        if ($this->pageHash === null || $this->pageHash === '') {
            return;
        }

        $percent = max(0, min(99, $percent));

        $current = (int) CompetitorsProgressBar::where('page_hash', '=', $this->pageHash)->value('percent');
        if ($percent < $current) {
            CompetitorAnalysisDebugLog::warn($this->pageHash, 'progress.skipped_regression', [
                'requested' => $percent,
                'current' => $current,
                'stage' => $stage,
            ]);

            return;
        }

        if ($percent !== $current) {
            CompetitorAnalysisDebugLog::info($this->pageHash, 'progress.update', [
                'from' => $current,
                'to' => $percent,
                'stage' => $stage,
            ]);
        }

        CompetitorsProgressBar::where('page_hash', '=', $this->pageHash)->update([
            'percent' => $percent,
        ]);
    }

    /**
     * Линейная шкала 1–98%: XML по всем (регион × фраза), затем scan/post по регионам.
     *
     * @param 'xml'|'scan'|'post' $phase
     */
    protected function computeOverallProgress(
        int $globalStepIndex,
        int $totalSteps,
        int $phrasesTotal,
        int $phraseIndex,
        string $phase,
        float $fraction
    ): int {
        $totalSteps = max(1, $totalSteps);
        $phrasesTotal = max(1, $phrasesTotal);
        $phraseIndex = max(0, min($phrasesTotal - 1, $phraseIndex));
        $fraction = max(0.0, min(1.0, $fraction));

        $xmlPct = (int) config('cabinet-competitor-analysis.progress_xml_percent', 15);
        $scanPct = (int) config('cabinet-competitor-analysis.progress_scan_percent', 80);
        $postPct = (int) config('cabinet-competitor-analysis.progress_post_percent', 3);
        $xmlEnd = 1 + $xmlPct;
        $scanEnd = $xmlEnd + $scanPct;

        if ($phase === 'xml') {
            $done = ($globalStepIndex * $phrasesTotal + $phraseIndex + $fraction)
                / ($totalSteps * $phrasesTotal);

            return max(1, min(99, $xmlEnd > 1 ? (int) round(1 + $done * ($xmlEnd - 1)) : 1));
        }

        if ($phase === 'scan') {
            $done = ($globalStepIndex + $fraction) / $totalSteps;

            return max($xmlEnd, min(99, $xmlEnd + (int) round($done * $scanPct)));
        }

        $done = ($globalStepIndex + $fraction) / $totalSteps;

        return max($scanEnd, min(99, $scanEnd + (int) round($done * $postPct)));
    }

    /**
     * @return Exception|void
     */
    public function analyseList()
    {
        @set_time_limit((int) config('cabinet-competitor-analysis.job_max_execution_sec', 1200));

        if (count($this->analysisPlan) === 0) {
            return new Exception('competitors error: no regions');
        }

        $this->countPhrases = 0;
        $this->byRegion = [];

        $totalSteps = 0;
        foreach ($this->analysisPlan as $planItem) {
            $totalSteps += count($planItem['regions'] ?? []);
        }

        if ($totalSteps === 0) {
            return new Exception('competitors error: no regions');
        }

        $this->updateProgressPercent(1, __('Processing the XML service response'));

        $globalStepIndex = 0;
        $phrasesList = array_values(array_filter(array_map('trim', $this->phrases)));
        $phrasesTotal = max(1, count($phrasesList));

        foreach ($this->analysisPlan as $planItem) {
            $this->searchEngine = \App\Support\CompetitorSearchRegions::normalizeEngine($planItem['engine'] ?? 'yandex');

            foreach ($planItem['regions'] as $regionMeta) {
                $this->region = (string) $regionMeta['id'];
                $regionKey = \App\Support\CompetitorSearchRegions::regionKey($this->searchEngine, $this->region);
                $this->resetRegionWorkingState();

                CompetitorAnalysisDebugLog::info($this->pageHash, 'region.step.start', [
                    'global_step' => $globalStepIndex,
                    'total_steps' => $totalSteps,
                    'region' => $this->region,
                    'engine' => $this->searchEngine,
                ]);

                $xml = new SimplifiedXmlFacade($this->region, $this->count);
                $xml->setDebugPageHash($this->pageHash);

                foreach ($phrasesList as $phraseIndex => $phrase) {
                    CompetitorAnalysisDebugLog::info($this->pageHash, 'region.xml.phrase.start', [
                        'global_step' => $globalStepIndex,
                        'phrase_index' => $phraseIndex,
                        'phrase' => $phrase,
                        'region' => $this->region,
                    ]);

                    $this->updateProgressPercent(
                        $this->computeOverallProgress(
                            $globalStepIndex,
                            $totalSteps,
                            $phrasesTotal,
                            $phraseIndex,
                            'xml',
                            0.05
                        ),
                        __('Processing the XML service response')
                    );

                    $providerTick = 0;
                    $xml->setProgressCallback(function () use ($globalStepIndex, $totalSteps, $phrasesTotal, $phraseIndex, &$providerTick) {
                        $providerTick++;
                        $part = min(0.95, $providerTick / 6.0);
                        $this->updateProgressPercent(
                            $this->computeOverallProgress(
                                $globalStepIndex,
                                $totalSteps,
                                $phrasesTotal,
                                $phraseIndex,
                                'xml',
                                $part
                            ),
                            __('Processing the XML service response')
                        );
                    });

                    $xml->setQuery($phrase);
                    $xml->setAttempt(0);
                    $response = $xml->getXMLResponse($this->searchEngine);

                    CompetitorAnalysisDebugLog::info($this->pageHash, 'region.xml.phrase.done', [
                        'global_step' => $globalStepIndex,
                        'phrase_index' => $phraseIndex,
                        'urls' => is_array($response) ? count($response) : 0,
                    ]);
                    if (is_array($response) && count($response) > 0) {
                        $this->sites[$phrase] = $response;
                    }

                    $this->updateProgressPercent(
                        $this->computeOverallProgress(
                            $globalStepIndex,
                            $totalSteps,
                            $phrasesTotal,
                            $phraseIndex,
                            'xml',
                            1.0
                        ),
                        __('Processing the XML service response')
                    );
                }

                if (count($this->sites) === 0) {
                    CompetitorAnalysisDebugLog::error($this->pageHash, 'xml.empty_after_phrases', [
                        'engine' => $this->searchEngine,
                        'region' => $this->region,
                        'phrases' => count($phrasesList),
                    ]);
                    self::markProgressFailed(
                        $this->pageHash,
                        __('The XML service returned no results. Check API keys (xmlstock) or try again later.')
                    );

                    return new Exception('competitors error: empty xml');
                }

                CompetitorAnalysisDebugLog::info($this->pageHash, 'scanSites.start', [
                    'sites_phrases' => count($this->sites),
                    'global_step' => $globalStepIndex,
                ]);

                foreach ($this->sites as $key => $site) {
                    if (is_array($site)) {
                        $this->countPhrases++;
                    } else {
                        unset($this->sites[$key]);
                    }
                }

                $isLastStep = ($globalStepIndex === $totalSteps - 1);

                try {
                    $this->scanSites($globalStepIndex, $totalSteps, $isLastStep, $regionKey);
                } catch (Throwable $e) {
                    Log::debug('search competitors exception', [
                        'message' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ]);

                    $now = Carbon::now();

                    SearchCompetitors::where('user_id', '=', $this->getUserId())
                        ->where('month', '=', $now->year . '-' . $now->month)
                        ->decrement('counter', $this->getCountPhrases());

                    self::markProgressFailed($this->pageHash, __('Analysis failed while parsing competitor sites.'));

                    return new Exception('competitors error');
                }

                $globalStepIndex++;
            }
        }

        if ($this->countPhrases > 0) {
            TariffSetting::saveStatistics(SearchCompetitors::class, $this->getUserId(), $this->countPhrases);
        }
    }

    protected function resetRegionWorkingState(): void
    {
        $this->sites = [];
        $this->analysedSites = [];
        $this->metaTags = [];
        $this->pagesCounter = [];
        $this->totalMetaTags = [];
        $this->domainsPosition = [];
        $this->urls = [];
    }

    /**
     * @return void
     */
    public function scanSites(int $globalStepIndex, int $totalSteps, bool $isLastStep, string $regionKey)
    {
        $phraseCount = max(1, count($this->phrases));
        $unitsPerRegion = max(1, $this->count * $phraseCount);

        $uniqueUrls = $this->collectUniqueSiteUrls();
        $progressEvery = max(1, (int) config('cabinet-competitor-analysis.progress_update_every_urls', 2));

        $phrasesTotal = max(1, count($this->phrases));
        $lastScanFraction = -1.0;
        $reportScanProgress = function (float $fraction) use ($globalStepIndex, $totalSteps, $phrasesTotal, &$lastScanFraction) {
            $fraction = max(0.0, min(1.0, $fraction));
            if ($fraction < 1.0 && $fraction - $lastScanFraction < 0.02) {
                return;
            }
            $lastScanFraction = $fraction;
            $this->updateProgressPercent(
                $this->computeOverallProgress(
                    $globalStepIndex,
                    $totalSteps,
                    $phrasesTotal,
                    0,
                    'scan',
                    $fraction
                )
            );
        };

        $this->prefetchSites(
            $uniqueUrls,
            function (int $fetched, int $total) use ($reportScanProgress, $progressEvery) {
                if ($total <= 0) {
                    return;
                }
                if ($fetched % $progressEvery !== 0 && $fetched !== $total) {
                    return;
                }
                $reportScanProgress($fetched / $total);
            }
        );

        $iterator = 0;
        foreach ($this->sites as $phrase => $items) {
            foreach ($items as $link) {
                if (! filter_var($link, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $this->analysedSites[$phrase][$link] = $this->duplicates[$link]
                    ?? $this->buildUnavailableSiteResult($link);

                $iterator++;
                if ($iterator % $progressEvery === 0 || $iterator === $unitsPerRegion) {
                    $reportScanProgress(min(1.0, $iterator / max(1, $unitsPerRegion)));
                }
            }
        }

        $this->analysisNestingDomains($globalStepIndex, $totalSteps, $isLastStep, $regionKey);
    }

    /**
     * @return array<int, string>
     */
    protected function collectUniqueSiteUrls(): array
    {
        $urls = [];
        foreach ($this->sites as $items) {
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $link) {
                if (is_string($link) && filter_var($link, FILTER_VALIDATE_URL)) {
                    $urls[$link] = true;
                }
            }
        }

        return array_keys($urls);
    }

    /**
     * @param array<int, string> $urls
     */
    protected function prefetchSites(array $urls, ?callable $onProgress = null): void
    {
        $pending = [];
        foreach ($urls as $link) {
            if (isset($this->duplicates[$link])) {
                continue;
            }
            if (self::shouldSkipSiteFetch($link)) {
                $this->duplicates[$link] = $this->buildUnavailableSiteResult($link);

                continue;
            }
            $pending[] = $link;
        }

        $total = count($pending);
        if ($total === 0) {
            return;
        }

        $useParallel = (bool) config('cabinet-competitor-analysis.site_curl_parallel', true);
        if ($useParallel && $total > 1) {
            $this->fetchSitesParallel($pending, $onProgress);

            return;
        }

        $fetched = 0;
        foreach ($pending as $link) {
            $this->fetchAndCacheSite($link);
            $fetched++;
            if ($onProgress !== null) {
                $onProgress($fetched, $total);
            }
        }
    }

    protected function fetchAndCacheSite(string $link): void
    {
        $curl = self::curlInit($link);
        $this->duplicates[$link] = $this->buildSiteResultFromCurl($curl, $link);
    }

    /**
     * @param array<int, string> $urls
     */
    protected function fetchSitesParallel(array $urls, ?callable $onProgress = null): void
    {
        $concurrency = max(1, min(20, (int) config('cabinet-competitor-analysis.site_curl_concurrency', 8)));
        $chunks = array_chunk($urls, $concurrency);
        $fetched = 0;
        $total = count($urls);

        foreach ($chunks as $chunk) {
            $multi = curl_multi_init();
            $handles = [];

            foreach ($chunk as $url) {
                $ch = self::createSiteCurlHandle($url);
                if ($ch === null) {
                    $this->duplicates[$url] = $this->buildUnavailableSiteResult($url);
                    $fetched++;
                    if ($onProgress !== null) {
                        $onProgress($fetched, $total);
                    }

                    continue;
                }
                curl_multi_add_handle($multi, $ch);
                $handles[$url] = $ch;
            }

            if (count($handles) > 0) {
                $running = null;
                do {
                    $status = curl_multi_exec($multi, $running);
                    if ($running > 0) {
                        curl_multi_select($multi, 0.25);
                    }
                } while ($running > 0 && $status === CURLM_OK);

                foreach ($handles as $url => $ch) {
                    $response = self::readSiteCurlResponse($ch);
                    curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                    if ($response === null) {
                        $response = self::curlInit($url);
                    }
                    $this->duplicates[$url] = $this->buildSiteResultFromCurl($response, $url);
                    $fetched++;
                    if ($onProgress !== null) {
                        $onProgress($fetched, $total);
                    }
                }
            }

            curl_multi_close($multi);
        }
    }

    /**
     * @param array|null $curl [html, headers] как curlInit()
     */
    protected function buildSiteResultFromCurl(?array $curl, string $link): array
    {
        if (! is_array($curl) || ! isset($curl[0]) || $curl[0] === null || $curl[0] === false) {
            return $this->buildUnavailableSiteResult($link);
        }

        return $this->analyseSite($this->encodingContent($curl), $link);
    }

    protected function buildUnavailableSiteResult(string $link, string $parseStatus = 'fetch_failed'): array
    {
        return [
            'meta' => self::emptyMetaStructure(),
            'danger' => $parseStatus === 'blocked',
            'parse_status' => $parseStatus,
            'mainPage' => self::isLinkMainPage($link),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected static function emptyMetaStructure(): array
    {
        return [
            'title' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'description' => [],
        ];
    }

    public static function shouldSkipSiteFetch(string $link): bool
    {
        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        $path = strtolower((string) parse_url($link, PHP_URL_PATH));

        if ($host === '') {
            return true;
        }

        // Не HTML-лендинги: картинки/поиск Яндекса — только тратят таймаут
        if (strpos($host, 'yandex.') !== false && strpos($path, '/images') !== false) {
            return true;
        }

        if (strpos($host, 'google.') !== false && strpos($path, '/search') !== false) {
            return true;
        }

        return false;
    }

    protected function analyseSite($site, $link): array
    {
        $html = self::responseBodyHtml($site);
        $meta = self::extractMetaFromHtml($html);

        $allEmpty = self::metaIsEmpty($meta);
        $parseStatus = 'ok';
        $danger = false;

        if ($allEmpty) {
            if (self::looksLikeBlockedPage($html)) {
                $parseStatus = 'blocked';
                $danger = true;
            } else {
                $parseStatus = 'meta_empty';
            }
        }

        return [
            'meta' => $meta,
            'danger' => $danger,
            'parse_status' => $parseStatus,
            'mainPage' => self::isLinkMainPage($link),
        ];
    }

    /**
     * @param array $site [html, headers]
     */
    protected static function responseBodyHtml(array $site): string
    {
        $raw = isset($site[0]) ? (string) $site[0] : '';
        if ($raw === '') {
            return '';
        }

        if (preg_match("/\r?\n\r?\n/s", $raw, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($raw, $pos);
        }

        return $raw;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function extractMetaFromHtml(string $html): array
    {
        if ($html === '') {
            return self::emptyMetaStructure();
        }

        $title = self::getText($html, '/<title[^>]*>(.*?)<\/title>/is');
        if ($title === []) {
            $ogTitle = self::getMetaContentByProperty($html, 'og:title');
            if ($ogTitle !== '') {
                $title = [$ogTitle];
            }
        }

        $description = self::getText($html, '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/is');
        if ($description === []) {
            $description = self::getText($html, '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/is');
        }
        if ($description === []) {
            $ogDesc = self::getMetaContentByProperty($html, 'og:description');
            if ($ogDesc !== '') {
                $description = [$ogDesc];
            }
        }

        return [
            'title' => $title,
            'h1' => self::getText($html, '/<h1[^>]*>(.*?)<\/h1>/is'),
            'h2' => self::getText($html, '/<h2[^>]*>(.*?)<\/h2>/is'),
            'h3' => self::getText($html, '/<h3[^>]*>(.*?)<\/h3>/is'),
            'h4' => self::getText($html, '/<h4[^>]*>(.*?)<\/h4>/is'),
            'h5' => self::getText($html, '/<h5[^>]*>(.*?)<\/h5>/is'),
            'h6' => self::getText($html, '/<h6[^>]*>(.*?)<\/h6>/is'),
            'description' => $description,
        ];
    }

    protected static function getMetaContentByProperty(string $html, string $property): string
    {
        if (preg_match(
            '/<meta[^>]+property=["\']' . preg_quote($property, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/is',
            $html,
            $m
        )) {
            return trim(htmlspecialchars_decode(strip_tags($m[1])));
        }
        if (preg_match(
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote($property, '/') . '["\']/is',
            $html,
            $m
        )) {
            return trim(htmlspecialchars_decode(strip_tags($m[1])));
        }

        return '';
    }

    /**
     * @param array<string, array<int, string>> $meta
     */
    protected static function metaIsEmpty(array $meta): bool
    {
        foreach ($meta as $values) {
            if (is_array($values) && count($values) > 0) {
                return false;
            }
        }

        return true;
    }

    public static function looksLikeBlockedPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }

        $sample = mb_substr(mb_strtolower($html), 0, 8000);

        $patterns = [
            'captcha',
            'recaptcha',
            'access denied',
            'request has been denied',
            'please confirm that you and not a robot',
            'подтвердите, что вы не робот',
            'доступ запрещ',
            'checking your browser',
            'enable javascript',
            'robot check',
            'cf-browser-verification',
            'ddos-guard',
        ];

        foreach ($patterns as $needle) {
            if (strpos($sample, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function encodingContent(array $site)
    {
        $html = self::responseBodyHtml($site);
        $charset = 'UTF-8';
        $contentType = $site[1]['content_type'] ?? '';

        if (preg_match('/<meta[^>]+charset=([\'"]?)([-a-zA-Z0-9]+)\1/i', $html, $matches)) {
            $charset = $matches[2];
        } elseif (preg_match('/charset=([a-zA-Z0-9\-_]+)/i', (string) $contentType, $matches)) {
            $charset = $matches[1];
        }

        if (strtoupper($charset) !== 'UTF-8' && $html !== '') {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if (is_string($converted) && $converted !== '') {
                $html = $converted;
            }
        }

        $site[0] = $html;

        return $site;
    }

    protected function analysisNestingDomains(int $globalStepIndex, int $totalSteps, bool $isLastStep, string $regionKey)
    {
        $this->pagesCounter = [
            'mainPageCounter' => 0,
            'nestedPageCounter' => 0,
        ];

        $counter = 0;
        foreach ($this->sites as $items) {
            foreach ($items as $item) {
                if (SearchCompetitors::isLinkMainPage($item)) {
                    $this->pagesCounter['mainPageCounter']++;
                } else {
                    $this->pagesCounter['nestedPageCounter']++;
                }
                $counter++;
            }
        }

        if ($counter > 0) {
            $this->pagesCounter['mainPagePercent'] = round((100 / $counter) * $this->pagesCounter['mainPageCounter'], 1);
            $this->pagesCounter['nestedPagePercent'] = round((100 / $counter) * $this->pagesCounter['nestedPageCounter'], 1);
        } else {
            $this->pagesCounter['mainPagePercent'] = 0;
            $this->pagesCounter['nestedPagePercent'] = 0;
        }

        $phrasesTotal = max(1, count($this->phrases));
        $this->updateProgressPercent(
            $this->computeOverallProgress(
                $globalStepIndex,
                $totalSteps,
                $phrasesTotal,
                0,
                'post',
                1.0
            )
        );

        $this->scanTags($isLastStep, $regionKey);
    }

    public function scanTags(bool $isLastStep, string $regionKey)
    {
        foreach ($this->analysedSites as $phrase => $sites) {
            foreach ($sites as $link => $site) {
                $this->metaTags[$phrase][] = $site['meta'];
            }
        }

        $metaTagsArray = ['title', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'description'];

        foreach ($metaTagsArray as $metaTag) {
            $this->searchMetaTag($metaTag);
        }

        $this->calculatePositions($isLastStep, $regionKey);
    }

    protected function searchMetaTag($key)
    {
        foreach ($this->metaTags as $phrase => $metaTags) {
            foreach ($metaTags as $metaTag) {
                $this->totalMetaTags[$phrase][$key][] = TextAnalyzer::deleteEverythingExceptCharacters(implode(' ', $metaTag[$key]));
            }
        }

        foreach ($this->metaTags as $phrase => $metaTags) {
            $this->totalMetaTags[$phrase][$key] = array_count_values(explode(' ', mb_strtolower(implode(' ', $this->totalMetaTags[$phrase][$key]))));

            arsort($this->totalMetaTags[$phrase][$key]);
        }
    }

    public function calculatePositions(bool $isLastStep, string $regionKey)
    {
        $domains = [];

        foreach ($this->analysedSites as $phrase => $sites) {
            $position = 1;
            foreach ($sites as $link => $item) {
                $host = parse_url($link)['host'];
                $domains[$host]['position'][] = $position;
                $domains[$host]['phrases'][] = $phrase;
                $domains[$host]['phrases'] = array_unique($domains[$host]['phrases']);
                $position++;
            }
        }

        $countPhrases = count($this->phrases);

        foreach ($domains as $domain => $info) {
            $countPositions = count($info['position']);
            $sum = array_sum($info['position']);
            $percent = $countPhrases / 100;

            $this->domainsPosition[$domain]['phrases'] = $info['phrases'];
            $this->domainsPosition[$domain]['topPercent'] = ceil(min(100, $countPositions / max(0.01, $percent)));
            $this->domainsPosition[$domain]['text'] = "($countPositions/$countPhrases)";

            if ($countPhrases === $countPositions || $countPhrases < $countPositions) {
                $this->domainsPosition[$domain]['avg'] = ceil($sum / max(1, $countPositions));
            } else {
                $this->domainsPosition[$domain]['avg'] = ceil(((($countPhrases - $countPositions) * $this->count + 1) + $sum) / max(1, $countPositions));
            }
        }

        $this->analysisRepeatUrl($isLastStep, $regionKey);
    }

    protected function analysisRepeatUrl(bool $isLastStep, string $regionKey)
    {
        $this->urls = [];
        foreach ($this->analysedSites as $phrase => $urls) {
            foreach ($urls as $url => $info) {
                if (isset($this->urls[$url])) {
                    $this->urls[$url]['count'] += 1;
                } else {
                    $this->urls[$url]['count'] = 1;
                }

                $this->urls[$url]['phrases'][] = $phrase;
            }
        }

        foreach ($this->urls as $url => $info) {
            $this->urls[$url]['phrases'] = array_unique($this->urls[$url]['phrases']);
        }

        $this->byRegion[$regionKey] = [
            'analysedSites' => $this->analysedSites,
            'pagesCounter' => $this->pagesCounter,
            'totalMetaTags' => $this->totalMetaTags,
            'domainsPosition' => $this->domainsPosition,
            'urls' => $this->urls,
        ];

        if ($isLastStep) {
            CompetitorAnalysisDebugLog::info($this->pageHash, 'progress.complete', [
                'regions' => array_keys($this->byRegion),
            ]);
            CompetitorsProgressBar::where('page_hash', '=', $this->pageHash)->update([
                'percent' => 100,
                'result' => $this->getResult(),
            ]);
        }
    }

    public static function getText($html, $regex): array
    {
        $hiddenText = [];
        preg_match_all($regex, $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] != "") {
                $hiddenText[] = htmlspecialchars_decode(strip_tags($match[1]));
            }
        }
        return $hiddenText;
    }

    public static function curlInit($site)
    {
        $curl = self::createSiteCurlHandle($site);
        if ($curl === null) {
            return null;
        }

        $result = self::tryConnect($curl);
        curl_close($curl);

        return $result;
    }

    /**
     * @return resource|null
     */
    public static function createSiteCurlHandle(string $site)
    {
        if (! filter_var($site, FILTER_VALIDATE_URL)) {
            return null;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $site);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $connectTimeout = (int) config('cabinet-competitor-analysis.site_curl_connect_timeout', 3);
        $timeout = (int) config('cabinet-competitor-analysis.site_curl_timeout', 4);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_USERAGENT, self::siteFetchUserAgents()[0]);

        return $curl;
    }

    /**
     * @param resource $curl
     */
    public static function readSiteCurlResponse($curl): ?array
    {
        $html = curl_exec($curl);
        $headers = curl_getinfo($curl);
        if ($html === false || ! is_array($headers)) {
            return null;
        }

        $code = (int) ($headers['http_code'] ?? 0);
        if ($code < 200 || $code >= 400) {
            $body = self::responseBodyHtml([$html, $headers]);
            if (strlen($body) < 80) {
                return null;
            }
        }

        return [$html, $headers];
    }

    /**
     * @param resource $curl
     */
    public static function tryConnect($curl): ?array
    {
        $userAgents = self::siteFetchUserAgents();
        $maxAttempts = (int) config('cabinet-competitor-analysis.site_curl_max_attempts', 2);
        $attempts = min($maxAttempts, count($userAgents));

        for ($i = 0; $i < $attempts; $i++) {
            curl_setopt($curl, CURLOPT_USERAGENT, $userAgents[$i]);
            $result = self::readSiteCurlResponse($curl);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected static function siteFetchUserAgents(): array
    {
        return [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
    }

    public static function isLinkMainPage($link): bool
    {
        $url = parse_url($link);

        try {
            return $url['path'] === '/' || $url['path'] === 'index.html' || $url['path'] === 'index.php';
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function countUniqueUsersSinceDays(int $days): int
    {
        if ($days < 1) {
            return 0;
        }

        return (int) static::query()
            ->where('updated_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
            ->value('aggregate');
    }
}
