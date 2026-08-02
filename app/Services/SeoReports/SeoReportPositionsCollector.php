<?php

namespace App\Services\SeoReports;

use App\MonitoringProject;
use App\Support\MonitoringProjectPublicStats;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeoReportPositionsCollector
{
    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collect(MonitoringProject $monitoring, ?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        $summary = MonitoringProjectPublicStats::summaryFromSnapshot($monitoring);
        if (empty($summary['has_data'])) {
            return [
                'ok' => false,
                'status' => 'empty',
                'message' => __('No position data yet'),
                'progress' => 'empty',
            ];
        }

        $dynamics = $this->dynamicsForPeriod(
            (int) $monitoring->id,
            $periodFrom ? $periodFrom->format('Y-m-d') : null,
            $periodTo ? $periodTo->format('Y-m-d') : null
        );

        $byEngine = $this->topByEngine((int) $monitoring->id);
        $visibility = $this->visibilitySeries(
            (int) $monitoring->id,
            $periodFrom ? $periodFrom->format('Y-m-d') : null,
            $periodTo ? $periodTo->format('Y-m-d') : null
        );
        $phrases = $this->phraseSamples(
            (int) $monitoring->id,
            $dynamics['date_from'] ?? null,
            $dynamics['date_to'] ?? null
        );
        $visibilityByEngine = $this->visibilityByEngine($byEngine);
        $baskets = $this->topBaskets($summary);
        $quickWins = $this->quickWinsFromPhrases($phrases);
        $risk = $this->riskFromPhrases($phrases);
        $groups = $this->groupsSummary((int) $monitoring->id);
        $competitors = $this->competitorsSummary($monitoring);

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'monitoring_project_id' => (int) $monitoring->id,
                'name' => $monitoring->name,
                'url' => $monitoring->url,
                'summary' => $summary,
                'dynamics' => $dynamics,
                'by_engine' => $byEngine,
                'visibility_series' => $visibility,
                'visibility_by_engine' => $visibilityByEngine,
                'top_baskets' => $baskets,
                'phrases' => $phrases,
                'quick_wins' => $quickWins,
                'risk' => $risk,
                'groups' => $groups,
                'competitors' => $competitors,
                'data_as_of' => Carbon::now()->toIso8601String(),
                'note' => !empty($dynamics['date_from']) && !empty($dynamics['date_to'])
                    ? __('Positions compared between :from and :to', [
                        'from' => Carbon::parse($dynamics['date_from'])->format('d.m.Y'),
                        'to' => Carbon::parse($dynamics['date_to'])->format('d.m.Y'),
                    ])
                    : __('Positions snapshot note'),
            ],
        ];
    }

    /**
     * @return list<array{id:int,name:string,words:int}>
     */
    private function groupsSummary(int $projectId): array
    {
        try {
            $rows = DB::table('monitoring_keywords as mk')
                ->leftJoin('monitoring_groups as g', 'g.id', '=', 'mk.monitoring_group_id')
                ->where('mk.monitoring_project_id', $projectId)
                ->groupBy('mk.monitoring_group_id', 'g.name')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(20)
                ->get([
                    DB::raw('mk.monitoring_group_id as id'),
                    DB::raw("COALESCE(g.name, 'Без группы') as name"),
                    DB::raw('COUNT(*) as words'),
                ]);
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row->id ?? 0),
                'name' => (string) ($row->name ?? '—'),
                'words' => (int) ($row->words ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{count:int,urls:list<string>}
     */
    private function competitorsSummary(MonitoringProject $monitoring): array
    {
        try {
            if (!method_exists($monitoring, 'competitors')) {
                return ['count' => 0, 'urls' => []];
            }
            $urls = $monitoring->competitors()->orderBy('id')->limit(15)->pluck('url')->filter()->values()->all();
        } catch (Throwable $e) {
            return ['count' => 0, 'urls' => []];
        }

        return [
            'count' => count($urls),
            'urls' => array_map('strval', $urls),
        ];
    }

    /**
     * @param array<string,mixed> $summary
     * @return list<array{label:string,value:int,diff:?string}>
     */
    private function topBaskets(array $summary): array
    {
        $out = [];
        foreach (['top1' => 'TOP-1', 'top3' => 'TOP-3', 'top5' => 'TOP-5', 'top10' => 'TOP-10', 'top30' => 'TOP-30', 'top100' => 'TOP-100'] as $key => $label) {
            if (!isset($summary[$key]) || $summary[$key] === '' || $summary[$key] === null) {
                continue;
            }
            $diffKey = 'diff_' . $key;
            $out[] = [
                'label' => $label,
                'value' => (int) $summary[$key],
                'diff' => isset($summary[$diffKey]) && $summary[$diffKey] !== '' && $summary[$diffKey] !== null
                    ? (string) $summary[$diffKey]
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $byEngine
     * @return list<array{engine:string,region:?string,pct:float,top10:int,words:int}>
     */
    private function visibilityByEngine(array $byEngine): array
    {
        $out = [];
        foreach ($byEngine as $row) {
            $words = (int) ($row['words'] ?? 0);
            $top10 = (int) ($row['top10'] ?? 0);
            $out[] = [
                'engine' => (string) ($row['engine'] ?? ''),
                'region' => $row['region'] ?? null,
                'top10' => $top10,
                'words' => $words,
                'pct' => $words > 0 ? round(100 * $top10 / $words, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param array{improved:list<array>,worsened:list<array>} $phrases
     * @return list<array>
     */
    private function quickWinsFromPhrases(array $phrases): array
    {
        $out = [];
        foreach (array_merge($phrases['improved'] ?? [], $phrases['worsened'] ?? []) as $row) {
            $pos = (int) ($row['pos_to'] ?? 0);
            if ($pos >= 8 && $pos <= 20) {
                $out[] = $row;
            }
        }
        usort($out, static function ($a, $b) {
            return ((int) ($a['pos_to'] ?? 99)) <=> ((int) ($b['pos_to'] ?? 99));
        });

        return array_slice($out, 0, 8);
    }

    /**
     * @param array{improved:list<array>,worsened:list<array>} $phrases
     * @return list<array>
     */
    private function riskFromPhrases(array $phrases): array
    {
        $out = [];
        foreach ($phrases['worsened'] ?? [] as $row) {
            $delta = (int) ($row['delta'] ?? 0);
            if (abs($delta) >= 5) {
                $out[] = $row;
            }
        }
        usort($out, static function ($a, $b) {
            return abs((int) ($b['delta'] ?? 0)) <=> abs((int) ($a['delta'] ?? 0));
        });

        return array_slice($out, 0, 8);
    }

    /**
     * @return array{improved:int,unchanged:int,worsened:int,pairs:int,date_from:?string,date_to:?string,by_engine:array}
     */
    private function dynamicsForPeriod(int $projectId, ?string $from, ?string $to): array
    {
        $empty = [
            'improved' => 0,
            'unchanged' => 0,
            'worsened' => 0,
            'pairs' => 0,
            'date_from' => null,
            'date_to' => null,
            'by_engine' => [],
        ];

        try {
            $dates = $this->resolveCompareDates($projectId, $from, $to);
            if ($dates === null) {
                return $empty;
            }

            [$dateFrom, $dateTo] = $dates;
            $all = $this->countDynamics($projectId, $dateFrom, $dateTo, null);
            $byEngine = [];
            foreach (['yandex', 'google'] as $engine) {
                $byEngine[$engine] = $this->countDynamics($projectId, $dateFrom, $dateTo, $engine);
            }

            return [
                'improved' => (int) $all['improved'],
                'unchanged' => (int) $all['unchanged'],
                'worsened' => (int) $all['worsened'],
                'pairs' => (int) $all['pairs'],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'by_engine' => $byEngine,
            ];
        } catch (Throwable $e) {
            return $empty;
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function resolveCompareDates(int $projectId, ?string $from, ?string $to): ?array
    {
        $available = DB::table('monitoring_positions as mp')
            ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
            ->where('mk.monitoring_project_id', $projectId)
            ->selectRaw('DATE(mp.created_at) as d')
            ->groupBy(DB::raw('DATE(mp.created_at)'))
            ->orderBy('d')
            ->pluck('d')
            ->filter()
            ->values()
            ->all();

        if (count($available) < 2) {
            return null;
        }

        if ($from && $to) {
            $dateFrom = $this->nearestOnOrAfter($available, $from) ?? $available[0];
            $dateTo = $this->nearestOnOrBefore($available, $to) ?? $available[count($available) - 1];
            if ($dateFrom >= $dateTo) {
                $dateFrom = $available[count($available) - 2];
                $dateTo = $available[count($available) - 1];
            }

            return [$dateFrom, $dateTo];
        }

        return [
            $available[count($available) - 2],
            $available[count($available) - 1],
        ];
    }

    /**
     * @param list<string> $dates
     */
    private function nearestOnOrAfter(array $dates, string $target): ?string
    {
        foreach ($dates as $d) {
            if ($d >= $target) {
                return $d;
            }
        }

        return null;
    }

    /**
     * @param list<string> $dates
     */
    private function nearestOnOrBefore(array $dates, string $target): ?string
    {
        $found = null;
        foreach ($dates as $d) {
            if ($d <= $target) {
                $found = $d;
            }
        }

        return $found;
    }

    /**
     * @return array{improved:int,unchanged:int,worsened:int,pairs:int}
     */
    private function countDynamics(int $projectId, string $dateFrom, string $dateTo, ?string $engine): array
    {
        $daySub = function (string $day) use ($projectId, $engine) {
            $q = DB::table('monitoring_positions as mp')
                ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
                ->join('monitoring_searchengines as se', 'se.id', '=', 'mp.monitoring_searchengine_id')
                ->where('mk.monitoring_project_id', $projectId)
                ->whereDate('mp.created_at', $day);
            if ($engine !== null) {
                $q->where('se.engine', $engine);
            }

            return $q->selectRaw('mp.monitoring_keyword_id, mp.monitoring_searchengine_id, MAX(mp.id) as id')
                ->groupBy('mp.monitoring_keyword_id', 'mp.monitoring_searchengine_id');
        };

        $row = DB::query()
            ->fromSub($daySub($dateFrom), 'a')
            ->joinSub($daySub($dateTo), 'b', function ($j) {
                $j->on('a.monitoring_keyword_id', '=', 'b.monitoring_keyword_id')
                    ->on('a.monitoring_searchengine_id', '=', 'b.monitoring_searchengine_id');
            })
            ->join('monitoring_positions as pa', 'pa.id', '=', 'a.id')
            ->join('monitoring_positions as pb', 'pb.id', '=', 'b.id')
            ->selectRaw('
                SUM(CASE WHEN pa.position - pb.position > 0 THEN 1 ELSE 0 END) AS improved,
                SUM(CASE WHEN pa.position - pb.position = 0 THEN 1 ELSE 0 END) AS unchanged,
                SUM(CASE WHEN pa.position - pb.position < 0 THEN 1 ELSE 0 END) AS worsened,
                COUNT(*) AS pairs
            ')
            ->first();

        return [
            'improved' => (int) ($row->improved ?? 0),
            'unchanged' => (int) ($row->unchanged ?? 0),
            'worsened' => (int) ($row->worsened ?? 0),
            'pairs' => (int) ($row->pairs ?? 0),
        ];
    }

    /**
     * Доля / число запросов в TOP-10 по дням периода.
     *
     * @return list<array{date:string,top10:int,words:int,pct:float}>
     */
    private function visibilitySeries(int $projectId, ?string $from, ?string $to): array
    {
        try {
            $q = DB::table('monitoring_positions as mp')
                ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
                ->where('mk.monitoring_project_id', $projectId)
                ->selectRaw('DATE(mp.created_at) as d')
                ->groupBy(DB::raw('DATE(mp.created_at)'))
                ->orderBy('d');
            if ($from) {
                $q->whereDate('mp.created_at', '>=', $from);
            }
            if ($to) {
                $q->whereDate('mp.created_at', '<=', $to);
            }
            $days = $q->pluck('d')->filter()->values()->all();
            if ($days === []) {
                return [];
            }

            // Не больше ~45 точек для графика
            if (count($days) > 45) {
                $step = (int) ceil(count($days) / 45);
                $sampled = [];
                for ($i = 0; $i < count($days); $i += $step) {
                    $sampled[] = $days[$i];
                }
                if (end($sampled) !== end($days)) {
                    $sampled[] = end($days);
                }
                $days = $sampled;
            }

            $out = [];
            foreach ($days as $day) {
                $latestIds = DB::table('monitoring_positions as mp')
                    ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
                    ->where('mk.monitoring_project_id', $projectId)
                    ->whereDate('mp.created_at', $day)
                    ->selectRaw('mp.monitoring_keyword_id, mp.monitoring_searchengine_id, MAX(mp.id) as id')
                    ->groupBy('mp.monitoring_keyword_id', 'mp.monitoring_searchengine_id');

                $row = DB::query()
                    ->fromSub($latestIds, 'x')
                    ->join('monitoring_positions as p', 'p.id', '=', 'x.id')
                    ->selectRaw('
                        COUNT(*) as words,
                        SUM(CASE WHEN p.position > 0 AND p.position <= 10 THEN 1 ELSE 0 END) as top10
                    ')
                    ->first();

                $words = (int) ($row->words ?? 0);
                $top10 = (int) ($row->top10 ?? 0);
                $out[] = [
                    'date' => (string) $day,
                    'top10' => $top10,
                    'words' => $words,
                    'pct' => $words > 0 ? round(100 * $top10 / $words, 1) : 0.0,
                ];
            }

            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Топ улучшившихся / ухудшившихся фраз с URL посадочной.
     *
     * @return array{improved:list<array>,worsened:list<array>}
     */
    private function phraseSamples(int $projectId, ?string $dateFrom, ?string $dateTo): array
    {
        $empty = ['improved' => [], 'worsened' => []];
        if (!$dateFrom || !$dateTo) {
            return $empty;
        }

        try {
            $daySub = function (string $day) use ($projectId) {
                return DB::table('monitoring_positions as mp')
                    ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
                    ->where('mk.monitoring_project_id', $projectId)
                    ->whereDate('mp.created_at', $day)
                    ->selectRaw('mp.monitoring_keyword_id, mp.monitoring_searchengine_id, MAX(mp.id) as id')
                    ->groupBy('mp.monitoring_keyword_id', 'mp.monitoring_searchengine_id');
            };

            $rows = DB::query()
                ->fromSub($daySub($dateFrom), 'a')
                ->joinSub($daySub($dateTo), 'b', function ($j) {
                    $j->on('a.monitoring_keyword_id', '=', 'b.monitoring_keyword_id')
                        ->on('a.monitoring_searchengine_id', '=', 'b.monitoring_searchengine_id');
                })
                ->join('monitoring_positions as pa', 'pa.id', '=', 'a.id')
                ->join('monitoring_positions as pb', 'pb.id', '=', 'b.id')
                ->join('monitoring_keywords as mk', 'mk.id', '=', 'a.monitoring_keyword_id')
                ->join('monitoring_searchengines as se', 'se.id', '=', 'a.monitoring_searchengine_id')
                ->select([
                    'mk.query',
                    'se.engine',
                    'pa.position as pos_from',
                    'pb.position as pos_to',
                    'pb.url as landing_url',
                    DB::raw('(pa.position - pb.position) as delta'),
                ])
                ->whereRaw('pa.position > 0 AND pb.position > 0')
                ->orderByRaw('(pa.position - pb.position) DESC')
                ->limit(40)
                ->get();

            $improved = [];
            $worsened = [];
            foreach ($rows as $row) {
                $item = [
                    'query' => (string) $row->query,
                    'engine' => (string) $row->engine,
                    'pos_from' => (int) $row->pos_from,
                    'pos_to' => (int) $row->pos_to,
                    'delta' => (int) $row->delta,
                    'url' => $row->landing_url ? (string) $row->landing_url : null,
                ];
                if ((int) $row->delta > 0 && count($improved) < 10) {
                    $improved[] = $item;
                }
            }

            $down = DB::query()
                ->fromSub($daySub($dateFrom), 'a')
                ->joinSub($daySub($dateTo), 'b', function ($j) {
                    $j->on('a.monitoring_keyword_id', '=', 'b.monitoring_keyword_id')
                        ->on('a.monitoring_searchengine_id', '=', 'b.monitoring_searchengine_id');
                })
                ->join('monitoring_positions as pa', 'pa.id', '=', 'a.id')
                ->join('monitoring_positions as pb', 'pb.id', '=', 'b.id')
                ->join('monitoring_keywords as mk', 'mk.id', '=', 'a.monitoring_keyword_id')
                ->join('monitoring_searchengines as se', 'se.id', '=', 'a.monitoring_searchengine_id')
                ->select([
                    'mk.query',
                    'se.engine',
                    'pa.position as pos_from',
                    'pb.position as pos_to',
                    'pb.url as landing_url',
                    DB::raw('(pa.position - pb.position) as delta'),
                ])
                ->whereRaw('pa.position > 0 AND pb.position > 0 AND (pa.position - pb.position) < 0')
                ->orderByRaw('(pa.position - pb.position) ASC')
                ->limit(10)
                ->get();

            foreach ($down as $row) {
                $worsened[] = [
                    'query' => (string) $row->query,
                    'engine' => (string) $row->engine,
                    'pos_from' => (int) $row->pos_from,
                    'pos_to' => (int) $row->pos_to,
                    'delta' => (int) $row->delta,
                    'url' => $row->landing_url ? (string) $row->landing_url : null,
                ];
            }

            return ['improved' => $improved, 'worsened' => $worsened];
        } catch (Throwable $e) {
            return $empty;
        }
    }

    /**
     * @return list<array{engine:string,region:?string,words:int,top3:int,top10:int,top30:int,top100:int}>
     */
    private function topByEngine(int $projectId): array
    {
        try {
            $engines = DB::table('monitoring_searchengines as se')
                ->leftJoin('locations as loc', 'loc.lr', '=', 'se.lr')
                ->where('se.monitoring_project_id', $projectId)
                ->orderBy('se.id')
                ->limit(20)
                ->get([
                    'se.id',
                    'se.engine',
                    'se.lr',
                    'loc.name as region_name',
                ]);
        } catch (Throwable $e) {
            $engines = DB::table('monitoring_searchengines')
                ->where('monitoring_project_id', $projectId)
                ->orderBy('id')
                ->limit(20)
                ->get(['id', 'engine', 'lr']);
        }

        $out = [];
        foreach ($engines as $se) {
            $engineId = (int) $se->id;
            $latestDay = DB::table('monitoring_positions as mp')
                ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
                ->where('mk.monitoring_project_id', $projectId)
                ->where('mp.monitoring_searchengine_id', $engineId)
                ->max(DB::raw('DATE(mp.created_at)'));

            $stats = [
                'words' => 0,
                'top3' => 0,
                'top10' => 0,
                'top30' => 0,
                'top100' => 0,
            ];

            if ($latestDay) {
                $latestIds = DB::table('monitoring_positions as mp')
                    ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
                    ->where('mk.monitoring_project_id', $projectId)
                    ->where('mp.monitoring_searchengine_id', $engineId)
                    ->whereDate('mp.created_at', $latestDay)
                    ->selectRaw('mp.monitoring_keyword_id, MAX(mp.id) as id')
                    ->groupBy('mp.monitoring_keyword_id');

                $row = DB::query()
                    ->fromSub($latestIds, 'x')
                    ->join('monitoring_positions as p', 'p.id', '=', 'x.id')
                    ->selectRaw('
                        COUNT(*) as words,
                        SUM(CASE WHEN p.position > 0 AND p.position <= 3 THEN 1 ELSE 0 END) as top3,
                        SUM(CASE WHEN p.position > 0 AND p.position <= 10 THEN 1 ELSE 0 END) as top10,
                        SUM(CASE WHEN p.position > 0 AND p.position <= 30 THEN 1 ELSE 0 END) as top30,
                        SUM(CASE WHEN p.position > 0 AND p.position <= 100 THEN 1 ELSE 0 END) as top100
                    ')
                    ->first();

                if ($row) {
                    $stats = [
                        'words' => (int) $row->words,
                        'top3' => (int) $row->top3,
                        'top10' => (int) $row->top10,
                        'top30' => (int) $row->top30,
                        'top100' => (int) $row->top100,
                    ];
                }
            }

            $out[] = [
                'engine' => (string) ($se->engine ?? ''),
                'region' => isset($se->region_name) ? (string) $se->region_name : (isset($se->lr) ? (string) $se->lr : null),
                'words' => $stats['words'],
                'top3' => $stats['top3'],
                'top10' => $stats['top10'],
                'top30' => $stats['top30'],
                'top100' => $stats['top100'],
            ];
        }

        return $out;
    }
}
