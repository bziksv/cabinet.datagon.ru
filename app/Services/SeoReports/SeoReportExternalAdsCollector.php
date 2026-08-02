<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use GuzzleHttp\Client;
use Throwable;

/**
 * VK Ads / Meta Ads / VK SMM: CSV import (+ optional VK community API token).
 * Full OAuth apps can replace CSV later without changing snapshot shape.
 */
class SeoReportExternalAdsCollector
{
    /**
     * @return array{ok:bool,status:string,progress:string,message?:string,data?:array<string,mixed>}
     */
    public function collect(string $key, SeoReportProject $project, SeoReport $report): array
    {
        $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
        $import = is_array($settings[$key . '_import'] ?? null) ? $settings[$key . '_import'] : null;

        $apiError = null;
        if ($key === 'vk_smm') {
            $api = $this->tryVkCommunityApi($settings, $report);
            if ($api['ok']) {
                return $api;
            }
            if (($api['status'] ?? '') === SeoReportSectionRegistry::SOURCE_STATUS_ERROR) {
                $apiError = $api;
            }
        }

        if ($import && $this->importHasData($import)) {
            return [
                'ok' => true,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
                'progress' => 'ok',
                'data' => $this->normalizeImportPayload($key, $import, $settings),
            ];
        }

        if ($apiError !== null) {
            return $apiError;
        }

        $tokenHint = $this->hasTokenConfigured($key, $settings);
        $messages = [
            'vk_ads' => $tokenHint
                ? __('Upload VK Ads CSV in settings (API sync later)')
                : __('VK Ads is not connected'),
            'meta_ads' => $tokenHint
                ? __('Upload Meta Ads CSV in settings (API sync later)')
                : __('Meta Ads is not connected'),
            'vk_smm' => $tokenHint
                ? __('VK community token set — upload CSV or check API rights')
                : __('VK community is not connected'),
        ];

        return [
            'ok' => false,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
            'progress' => 'skip',
            'message' => $messages[$key] ?? __('Source is not connected yet'),
        ];
    }

    /**
     * Flexible CSV for ads/SMM exports.
     *
     * @return array<string,mixed>|null
     */
    public function parseCsv(string $path, string $source): ?array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return null;
        }
        $delimiter = ',';
        $header = fgetcsv($fh, 0, ',');
        if ($header === false || !is_array($header) || count($header) < 2) {
            rewind($fh);
            $header = fgetcsv($fh, 0, ';');
            $delimiter = ';';
        }
        if (!is_array($header) || $header === []) {
            fclose($fh);

            return null;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $map = [];
        foreach ($header as $i => $col) {
            $map[mb_strtolower(trim((string) $col))] = $i;
        }
        $pick = static function (array $map, array $aliases) {
            foreach ($aliases as $a) {
                if (isset($map[$a])) {
                    return $map[$a];
                }
            }

            return null;
        };
        $num = static function ($raw) {
            return (float) str_replace(['%', ',', ' ', '₽', '$', '€'], ['', '.', '', '', '', ''], (string) $raw);
        };

        $iCampaign = $pick($map, ['campaign', 'кампания', 'campaign name', 'название кампании']);
        $iAd = $pick($map, ['ad', 'ads', 'ad name', 'объявление', 'creative', 'креатив', 'название']);
        $iDate = $pick($map, ['date', 'день', 'дата', 'day']);
        $iAge = $pick($map, ['age', 'возраст']);
        $iGender = $pick($map, ['gender', 'пол', 'sex']);
        $iImpr = $pick($map, ['impressions', 'показы', 'views', 'просмотры']);
        $iClicks = $pick($map, ['clicks', 'клики']);
        $iReach = $pick($map, ['reach', 'охват']);
        $iSpend = $pick($map, ['spend', 'cost', 'расход', 'spent', 'amount']);
        $iCtr = $pick($map, ['ctr']);
        $iCpc = $pick($map, ['cpc']);
        $iCpm = $pick($map, ['cpm']);
        $iSubs = $pick($map, ['subscribers', 'подписчики', 'members', 'участники']);
        $iLikes = $pick($map, ['likes', 'лайки']);
        $iComments = $pick($map, ['comments', 'комменты', 'комментарии']);
        $iShares = $pick($map, ['shares', 'репосты', 'reposts']);
        $iVisitors = $pick($map, ['visitors', 'посетители']);
        $iPosts = $pick($map, ['posts', 'публикации', 'post']);
        $iEr = $pick($map, ['er', 'engagement rate', 'вовлечённость']);
        $iPostTitle = $pick($map, ['post', 'пост', 'publication', 'текст', 'title']);

        $campaigns = [];
        $ads = [];
        $demography = [];
        $dynamics = [];
        $topPosts = [];
        $sum = [
            'impressions' => 0.0,
            'clicks' => 0.0,
            'reach' => 0.0,
            'spend' => 0.0,
            'subscribers' => 0.0,
            'likes' => 0.0,
            'comments' => 0.0,
            'shares' => 0.0,
            'visitors' => 0.0,
            'posts' => 0.0,
        ];
        $lastSubs = null;

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row)) {
                continue;
            }
            $impr = $iImpr !== null ? $num($row[$iImpr] ?? 0) : 0.0;
            $clicks = $iClicks !== null ? $num($row[$iClicks] ?? 0) : 0.0;
            $reach = $iReach !== null ? $num($row[$iReach] ?? 0) : 0.0;
            $spend = $iSpend !== null ? $num($row[$iSpend] ?? 0) : 0.0;
            $likes = $iLikes !== null ? $num($row[$iLikes] ?? 0) : 0.0;
            $comments = $iComments !== null ? $num($row[$iComments] ?? 0) : 0.0;
            $shares = $iShares !== null ? $num($row[$iShares] ?? 0) : 0.0;
            $visitors = $iVisitors !== null ? $num($row[$iVisitors] ?? 0) : 0.0;
            $posts = $iPosts !== null ? $num($row[$iPosts] ?? 0) : 0.0;
            $subs = $iSubs !== null ? $num($row[$iSubs] ?? 0) : null;
            $ctr = $iCtr !== null ? $num($row[$iCtr] ?? 0) : ($impr > 0 ? round($clicks / $impr * 100, 2) : null);
            $cpc = $iCpc !== null ? $num($row[$iCpc] ?? 0) : ($clicks > 0 ? round($spend / $clicks, 2) : null);
            $cpm = $iCpm !== null ? $num($row[$iCpm] ?? 0) : ($impr > 0 ? round($spend / $impr * 1000, 2) : null);
            $er = $iEr !== null ? $num($row[$iEr] ?? 0) : null;

            $sum['impressions'] += $impr;
            $sum['clicks'] += $clicks;
            $sum['reach'] += $reach;
            $sum['spend'] += $spend;
            $sum['likes'] += $likes;
            $sum['comments'] += $comments;
            $sum['shares'] += $shares;
            $sum['visitors'] += $visitors;
            $sum['posts'] += $posts;
            if ($subs !== null) {
                $lastSubs = $subs;
                $sum['subscribers'] = max($sum['subscribers'], $subs);
            }

            if ($iCampaign !== null && trim((string) ($row[$iCampaign] ?? '')) !== '') {
                $name = trim((string) $row[$iCampaign]);
                if (!isset($campaigns[$name])) {
                    $campaigns[$name] = [
                        'name' => $name,
                        'impressions' => 0.0,
                        'clicks' => 0.0,
                        'reach' => 0.0,
                        'spend' => 0.0,
                    ];
                }
                $campaigns[$name]['impressions'] += $impr;
                $campaigns[$name]['clicks'] += $clicks;
                $campaigns[$name]['reach'] += $reach;
                $campaigns[$name]['spend'] += $spend;
            }

            if ($iAd !== null && trim((string) ($row[$iAd] ?? '')) !== '') {
                $name = trim((string) $row[$iAd]);
                if (!isset($ads[$name])) {
                    $ads[$name] = [
                        'name' => $name,
                        'impressions' => 0.0,
                        'clicks' => 0.0,
                        'spend' => 0.0,
                    ];
                }
                $ads[$name]['impressions'] += $impr;
                $ads[$name]['clicks'] += $clicks;
                $ads[$name]['spend'] += $spend;
            }

            if (($iAge !== null || $iGender !== null) && ($clicks > 0 || $impr > 0)) {
                $label = trim(
                    ($iGender !== null ? (string) ($row[$iGender] ?? '') : '')
                    . ($iAge !== null ? ' ' . (string) ($row[$iAge] ?? '') : '')
                );
                if ($label !== '') {
                    if (!isset($demography[$label])) {
                        $demography[$label] = ['name' => $label, 'clicks' => 0.0, 'impressions' => 0.0];
                    }
                    $demography[$label]['clicks'] += $clicks;
                    $demography[$label]['impressions'] += $impr;
                }
            }

            if ($iDate !== null && trim((string) ($row[$iDate] ?? '')) !== '') {
                $date = trim((string) $row[$iDate]);
                $dynamics[] = [
                    'date' => $date,
                    'subscribers' => $subs,
                    'reach' => $reach,
                    'views' => $impr,
                    'likes' => $likes,
                    'comments' => $comments,
                    'shares' => $shares,
                ];
            }

            if ($source === 'vk_smm' && $iPostTitle !== null && trim((string) ($row[$iPostTitle] ?? '')) !== '') {
                $topPosts[] = [
                    'name' => trim((string) $row[$iPostTitle]),
                    'likes' => $likes,
                    'comments' => $comments,
                    'shares' => $shares,
                    'reach' => $reach,
                    'views' => $impr,
                    'er' => $er,
                ];
            }
        }
        fclose($fh);

        foreach ($campaigns as &$c) {
            $c['ctr'] = $c['impressions'] > 0 ? round($c['clicks'] / $c['impressions'] * 100, 2) : null;
            $c['cpc'] = $c['clicks'] > 0 ? round($c['spend'] / $c['clicks'], 2) : null;
            $c['cpm'] = $c['impressions'] > 0 ? round($c['spend'] / $c['impressions'] * 1000, 2) : null;
        }
        unset($c);
        foreach ($ads as &$a) {
            $a['ctr'] = $a['impressions'] > 0 ? round($a['clicks'] / $a['impressions'] * 100, 2) : null;
            $a['cpc'] = $a['clicks'] > 0 ? round($a['spend'] / $a['clicks'], 2) : null;
        }
        unset($a);

        usort($campaigns, static function ($a, $b) {
            return ($b['spend'] ?? $b['clicks']) <=> ($a['spend'] ?? $a['clicks']);
        });
        usort($ads, static function ($a, $b) {
            return ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0);
        });
        usort($demography, static function ($a, $b) {
            return ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0);
        });
        usort($topPosts, static function ($a, $b) {
            return (($b['likes'] ?? 0) + ($b['comments'] ?? 0)) <=> (($a['likes'] ?? 0) + ($a['comments'] ?? 0));
        });

        $hasAny = $sum['impressions'] > 0 || $sum['clicks'] > 0 || $sum['reach'] > 0
            || $sum['spend'] > 0 || $sum['subscribers'] > 0 || $sum['likes'] > 0
            || $campaigns !== [] || $ads !== [] || $topPosts !== [] || $dynamics !== [];
        if (!$hasAny) {
            return null;
        }

        $engagement = $sum['likes'] + $sum['comments'] + $sum['shares'];
        $er = null;
        if ($sum['reach'] > 0 && $engagement > 0) {
            $er = round($engagement / $sum['reach'] * 100, 2);
        } elseif ($lastSubs !== null && $lastSubs > 0 && $engagement > 0) {
            $er = round($engagement / $lastSubs * 100, 2);
        }

        return [
            'imported_at' => now()->toIso8601String(),
            'kpis' => [
                'reach' => $sum['reach'] ?: null,
                'impressions' => $sum['impressions'] ?: null,
                'clicks' => $sum['clicks'] ?: null,
                'ctr' => $sum['impressions'] > 0 ? round($sum['clicks'] / $sum['impressions'] * 100, 2) : null,
                'cpc' => $sum['clicks'] > 0 ? round($sum['spend'] / $sum['clicks'], 2) : null,
                'cpm' => $sum['impressions'] > 0 ? round($sum['spend'] / $sum['impressions'] * 1000, 2) : null,
                'spend' => $sum['spend'] ?: null,
                'subscribers' => $sum['subscribers'] ?: $lastSubs,
                'visitors' => $sum['visitors'] ?: null,
                'likes' => $sum['likes'] ?: null,
                'comments' => $sum['comments'] ?: null,
                'shares' => $sum['shares'] ?: null,
                'posts' => $sum['posts'] ?: null,
                'er' => $er,
            ],
            'campaigns' => array_values($campaigns),
            'ads' => array_values($ads),
            'demography' => array_values($demography),
            'dynamics' => $dynamics,
            'top_posts' => array_slice($topPosts, 0, 20),
            'post_stats' => array_slice($topPosts, 0, 50),
        ];
    }

    /**
     * @param array<string,mixed> $import
     */
    private function importHasData(array $import): bool
    {
        return !empty($import['kpis'])
            || !empty($import['campaigns'])
            || !empty($import['ads'])
            || !empty($import['top_posts'])
            || !empty($import['dynamics']);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function hasTokenConfigured(string $key, array $settings): bool
    {
        if ($key === 'vk_ads') {
            return trim((string) ($settings['vk_ads_token'] ?? '')) !== '';
        }
        if ($key === 'meta_ads') {
            return trim((string) ($settings['meta_ads_token'] ?? '')) !== '';
        }
        if ($key === 'vk_smm') {
            return trim((string) ($settings['vk_smm_token'] ?? '')) !== ''
                && trim((string) ($settings['vk_smm_group_id'] ?? '')) !== '';
        }

        return false;
    }

    /**
     * @param array<string,mixed> $import
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function normalizeImportPayload(string $key, array $import, array $settings): array
    {
        $notes = [
            'vk_ads' => __('VK Ads from CSV (token/API sync later)'),
            'meta_ads' => __('Meta Ads from CSV (Marketing API later)'),
            'vk_smm' => __('VK community from CSV / token'),
        ];

        return [
            'source' => 'csv_import',
            'account' => $settings[$key . '_account'] ?? ($settings['vk_smm_group_id'] ?? null),
            'imported_at' => $import['imported_at'] ?? null,
            'note' => $notes[$key] ?? __('Data from CSV import'),
            'kpis' => $import['kpis'] ?? [],
            'campaigns' => $import['campaigns'] ?? [],
            'ads' => $import['ads'] ?? [],
            'demography' => $import['demography'] ?? [],
            'dynamics' => $import['dynamics'] ?? [],
            'top_posts' => $import['top_posts'] ?? [],
            'post_stats' => $import['post_stats'] ?? ($import['top_posts'] ?? []),
            'engagement' => [
                'likes' => $import['kpis']['likes'] ?? null,
                'comments' => $import['kpis']['comments'] ?? null,
                'shares' => $import['kpis']['shares'] ?? null,
                'er' => $import['kpis']['er'] ?? null,
            ],
            'reach_views' => [
                'reach' => $import['kpis']['reach'] ?? null,
                'views' => $import['kpis']['impressions'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @return array{ok:bool,status:string,progress:string,message?:string,data?:array<string,mixed>}
     */
    private function tryVkCommunityApi(array $settings, SeoReport $report): array
    {
        $token = trim((string) ($settings['vk_smm_token'] ?? ''));
        $groupId = (int) preg_replace('/\D+/', '', (string) ($settings['vk_smm_group_id'] ?? ''));
        if ($token === '' || $groupId < 1) {
            return ['ok' => false, 'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED, 'progress' => 'skip'];
        }

        $date1 = optional($report->period_from)->timestamp;
        $date2 = optional($report->period_to)->copy()->endOfDay()->timestamp;
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'progress' => 'error',
                'message' => __('Invalid period'),
            ];
        }

        try {
            $client = new Client(['timeout' => 20, 'http_errors' => false]);
            $group = $this->vkCall($client, 'groups.getById', [
                'group_id' => $groupId,
                'fields' => 'members_count',
                'access_token' => $token,
            ]);
            $members = null;
            if (is_array($group['response'][0] ?? null)) {
                $members = (float) ($group['response'][0]['members_count'] ?? 0);
            }

            $stats = $this->vkCall($client, 'stats.get', [
                'group_id' => $groupId,
                'timestamp_from' => $date1,
                'timestamp_to' => $date2,
                'interval' => 'day',
                'stats_groups' => 'visitors,reach,activity',
                'access_token' => $token,
            ]);
            $wall = $this->vkCall($client, 'wall.get', [
                'owner_id' => -$groupId,
                'count' => 15,
                'access_token' => $token,
            ]);

            $dynamics = [];
            $reach = 0.0;
            $views = 0.0;
            $visitors = 0.0;
            $likes = 0.0;
            $comments = 0.0;
            $shares = 0.0;
            $posts = 0.0;
            foreach (($stats['response'] ?? []) as $day) {
                if (!is_array($day)) {
                    continue;
                }
                $vis = (float) ($day['visitors']['visitors'] ?? $day['visitors'] ?? 0);
                $r = (float) ($day['reach']['reach'] ?? $day['reach'] ?? 0);
                $v = (float) ($day['visitors']['views'] ?? $day['views'] ?? 0);
                $act = is_array($day['activity'] ?? null) ? $day['activity'] : [];
                $l = (float) ($act['likes'] ?? 0);
                $c = (float) ($act['comments'] ?? 0);
                $s = (float) ($act['copies'] ?? $act['shares'] ?? 0);
                $p = (float) ($act['subscribed'] ?? 0);
                $reach += $r;
                $views += $v;
                $visitors += $vis;
                $likes += $l;
                $comments += $c;
                $shares += $s;
                $dynamics[] = [
                    'date' => isset($day['period_from'])
                        ? date('Y-m-d', (int) $day['period_from'])
                        : null,
                    'subscribers' => $members,
                    'reach' => $r,
                    'views' => $v,
                    'likes' => $l,
                    'comments' => $c,
                    'shares' => $s,
                ];
            }

            $topPosts = [];
            foreach (($wall['response']['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $posts++;
                $text = trim((string) ($item['text'] ?? ''));
                if (mb_strlen($text) > 80) {
                    $text = mb_substr($text, 0, 77) . '…';
                }
                $topPosts[] = [
                    'name' => $text !== '' ? $text : ('post #' . ($item['id'] ?? '')),
                    'likes' => (float) ($item['likes']['count'] ?? 0),
                    'comments' => (float) ($item['comments']['count'] ?? 0),
                    'shares' => (float) ($item['reposts']['count'] ?? 0),
                    'views' => (float) ($item['views']['count'] ?? 0),
                    'reach' => null,
                ];
            }

            if ($members === null && $reach <= 0 && $topPosts === []) {
                $err = (string) ($stats['error']['error_msg'] ?? $group['error']['error_msg'] ?? '');

                return [
                    'ok' => false,
                    'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                    'progress' => 'error',
                    'message' => $err !== '' ? $err : __('Could not load VK community stats'),
                ];
            }

            $engagement = $likes + $comments + $shares;
            $er = ($reach > 0 && $engagement > 0)
                ? round($engagement / $reach * 100, 2)
                : (($members > 0 && $engagement > 0) ? round($engagement / $members * 100, 2) : null);

            // Demography: VK stats may include sex/age in visitors — keep empty if absent.
            $demography = [];
            foreach (($stats['response'][0]['visitors']['sex_age'] ?? []) as $sa) {
                if (!is_array($sa)) {
                    continue;
                }
                $demography[] = [
                    'name' => trim(($sa['sex'] ?? '') . ' ' . ($sa['age_range'] ?? $sa['age'] ?? '')),
                    'clicks' => (float) ($sa['count'] ?? 0),
                    'impressions' => null,
                ];
            }

            return [
                'ok' => true,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
                'progress' => 'ok',
                'data' => [
                    'source' => 'vk_api',
                    'account' => $groupId,
                    'note' => __('VK community via API token'),
                    'kpis' => [
                        'subscribers' => $members,
                        'reach' => $reach ?: null,
                        'impressions' => $views ?: null,
                        'visitors' => $visitors ?: null,
                        'likes' => $likes ?: null,
                        'comments' => $comments ?: null,
                        'shares' => $shares ?: null,
                        'posts' => $posts ?: null,
                        'er' => $er,
                    ],
                    'dynamics' => $dynamics,
                    'top_posts' => array_slice($topPosts, 0, 15),
                    'post_stats' => $topPosts,
                    'demography' => $demography,
                    'engagement' => [
                        'likes' => $likes ?: null,
                        'comments' => $comments ?: null,
                        'shares' => $shares ?: null,
                        'er' => $er,
                    ],
                    'reach_views' => [
                        'reach' => $reach ?: null,
                        'views' => $views ?: null,
                    ],
                    'campaigns' => [],
                    'ads' => [],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'progress' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function vkCall(Client $client, string $method, array $params): array
    {
        $params['v'] = $params['v'] ?? '5.199';
        $res = $client->get('https://api.vk.com/method/' . $method, ['query' => $params]);
        $json = json_decode((string) $res->getBody(), true);

        return is_array($json) ? $json : [];
    }
}
