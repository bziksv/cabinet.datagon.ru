@php
    $snapshot = $snapshot ?? [];
    $cover = $snapshot['cover'] ?? [];
    $traffic = $snapshot['traffic'] ?? null;
    $positions = $snapshot['positions'] ?? null;
    $conversions = $snapshot['conversions'] ?? null;
    $kpiGoalsEval = is_array($snapshot['kpi_goals'] ?? null) ? $snapshot['kpi_goals'] : [];
    $quality = $snapshot['quality'] ?? null;
    $scorecard = $snapshot['scorecard'] ?? [];
    $insights = $snapshot['insights'] ?? [];
    $comments = is_array($report->comments_json ?? null) ? $report->comments_json : [];
    $isPublic = !empty($isPublicView);
    $brand = \App\SeoReports\SeoReportBrandColor::normalize(
        $cover['agency']['brand_color'] ?? ($project->brand_color ?: '#1d4ed8')
    );
    $projectSettings = method_exists($project, 'reportSettings')
        ? $project->reportSettings()
        : (is_array($project->settings_json ?? null) ? $project->settings_json : []);
    $mirrorDomains = is_array($projectSettings['mirror_domains'] ?? null) ? $projectSettings['mirror_domains'] : [];
    $confirmedOnly = !empty($projectSettings['confirmed_sources_only']);
    $metricOn = static function (string $section, string $metric) use ($projectSettings): bool {
        return \App\SeoReports\SeoReportMetricRegistry::enabled($projectSettings, $section, $metric);
    };
    $kpiLabels = [
        'visits' => __('Visits'),
        'users' => __('Users'),
        'pageviews' => __('Pageviews'),
        'bounce_rate' => __('Bounce rate'),
        'page_depth' => __('Page depth'),
        'avg_visit_duration' => __('Avg. visit duration'),
    ];
    $toc = [];
    foreach ($sections as $section) {
        $key = $section['key'];
        if ($key === 'cover') {
            continue;
        }
        $enabled = array_key_exists('enabled', $section) ? !empty($section['enabled']) : true;
        $clientVisible = array_key_exists('client_visible', $section) ? !empty($section['client_visible']) : true;
        if ($isPublic && !$clientVisible) {
            continue;
        }
        if (!$enabled && $isPublic) {
            continue;
        }
        $toc[] = ['key' => $key, 'title' => $section['title']];
    }

    $fmtDur = static function ($sec) {
        $sec = (int) round((float) $sec);
        return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    };
    $fmtNum = static function ($v, $decimals = 0) {
        return number_format((float) $v, $decimals, ',', ' ');
    };
@endphp

<div class="cabinet-sr-report {{ count($toc) > 1 ? 'cabinet-sr-report--with-toc' : '' }}"
     style="--sr-accent: {{ $brand }};"
     data-sr-report>
    @if(count($toc) > 1)
        <aside class="cabinet-sr-toc" data-sr-toc aria-label="{{ __('Report sections') }}">
            <div class="cabinet-sr-toc__head">
                <div class="cabinet-sr-toc__title">{{ __('Report sections') }}</div>
                <div class="cabinet-sr-toc__bar" data-sr-toc-bar></div>
            </div>
            <nav class="cabinet-sr-toc__links">
                @foreach($toc as $item)
                    <a href="#sr-{{ $item['key'] }}"
                       data-sr-toc-link="sr-{{ $item['key'] }}"
                       data-sr-section-jump="{{ $item['key'] }}">{{ $item['title'] }}</a>
                @endforeach
            </nav>
        </aside>
    @endif

    <div class="cabinet-sr-report__main">
    @if(!$isPublic)
        <label class="cabinet-sr-compare-toggle">
            <input type="checkbox" checked data-sr-compare-toggle>
            <span>{{ __('Show period compare') }}</span>
        </label>
    @endif

    <section class="cabinet-sr-report-block cabinet-sr-cover" id="sr-cover">
        <div class="cabinet-sr-cover__accent" aria-hidden="true"></div>
        <div class="cabinet-sr-cover__row">
            <div class="cabinet-sr-cover__main">
                @if(!empty($cover['agency']['logo_url']))
                    <img class="cabinet-sr-cover__logo" src="{{ $cover['agency']['logo_url'] }}" alt="">
                @elseif(!empty($cover['agency']['name']))
                    <div class="cabinet-sr-cover__agency">{{ $cover['agency']['name'] }}</div>
                @endif
                <h1 class="cabinet-sr-cover__title">{{ $cover['title'] ?? ($project->domain) }}</h1>
                <p class="cabinet-sr-cover__period">{{ $cover['period_label'] ?? '' }}</p>
                @if($mirrorDomains !== [])
                    <p class="cabinet-sr-cover__meta">
                        {{ __('Mirror domains') }}: {{ implode(', ', $mirrorDomains) }}
                    </p>
                @endif
                @if(!empty($cover['compare_label']))
                    <p class="cabinet-sr-cover__compare">
                        {{ __('Compare') }}: {{ $cover['compare_label'] }}
                        @if(!empty($cover['compare_baseline']['reason']))
                            <span>({{ $cover['compare_baseline']['reason'] }})</span>
                        @endif
                    </p>
                @endif
                @if(!empty($cover['data_as_of']))
                    <p class="cabinet-sr-cover__meta">{{ __('Data as of') }}: {{ \Carbon\Carbon::parse($cover['data_as_of'])->format('d.m.Y H:i') }}</p>
                @endif
                @if($quality)
                    <span class="cabinet-sr-badge {{ $quality === 'full' ? 'cabinet-sr-badge--ok' : 'cabinet-sr-badge--warn' }}">
                        {{ $quality === 'full' ? __('Full data') : ($quality === 'partial' ? __('Partial data') : __('No data')) }}
                    </span>
                @endif
            </div>
            <div class="cabinet-sr-cover__manager">
                @if(!empty($cover['manager']['avatar_url']))
                    <img class="cabinet-sr-cover__avatar" src="{{ $cover['manager']['avatar_url'] }}" alt="">
                @endif
                @if(!empty($cover['manager']['name']))
                    <div class="cabinet-sr-cover__manager-label">{{ __('Your manager') }}</div>
                    <div class="cabinet-sr-cover__manager-name">{{ $cover['manager']['name'] }}</div>
                    @if(!empty($cover['manager']['phone']))
                        <a href="tel:{{ preg_replace('/\s+/', '', $cover['manager']['phone']) }}">{{ $cover['manager']['phone'] }}</a>
                    @endif
                    @if(!empty($cover['manager']['email']))
                        <div><a href="mailto:{{ $cover['manager']['email'] }}">{{ $cover['manager']['email'] }}</a></div>
                    @endif
                @endif
            </div>
        </div>
    </section>

    @if(!empty($scorecard))
        <section class="cabinet-sr-scorecard" id="sr-scorecard">
            @foreach($scorecard as $card)
                <div class="cabinet-sr-kpi">
                    <div class="cabinet-sr-kpi__label">
                        <span class="cabinet-sr-tip" title="{{ __('Metric tip: :name', ['name' => $card['label']]) }}">{{ $card['label'] }}</span>
                    </div>
                    <div class="cabinet-sr-kpi__value">{{ $card['value'] }}</div>
                    @if(!empty($card['delta']))
                        <div class="cabinet-sr-kpi__delta {{ $card['delta_class'] ?? '' }}">{{ $card['delta'] }}</div>
                    @endif
                    @if(($card['key'] ?? '') === 'visits' && !empty($traffic['series_users']))
                        <div class="cabinet-sr-spark cabinet-sr-spark--mini" aria-hidden="true">
                            @php
                                $vals = array_values($traffic['series_users']);
                                $max = max(1, max($vals ?: [1]));
                            @endphp
                            @foreach(array_slice($vals, -14) as $v)
                                <span style="height: {{ max(4, (int) round(28 * $v / $max)) }}px"></span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if(!empty($kpiGoalsEval))
        <section class="cabinet-sr-goals-strip" id="sr-kpi-goals-strip">
            @foreach($kpiGoalsEval as $g)
                @php
                    $pctBar = $g['pct'] !== null ? max(0, min(100, (float) $g['pct'])) : 0;
                @endphp
                <div class="cabinet-sr-goal-card cabinet-sr-goal-card--{{ $g['tone'] ?? 'yellow' }}">
                    <div class="cabinet-sr-goal-card__label">{{ $g['label'] }}</div>
                    <div class="cabinet-sr-goal-card__pct">
                        {{ $g['pct'] !== null ? $fmtNum($g['pct'], 1) . '%' : '—' }}
                    </div>
                    <div class="cabinet-sr-goal-card__bar" aria-hidden="true">
                        <i style="width: {{ $pctBar }}%"></i>
                    </div>
                    <div class="cabinet-sr-goal-card__meta">
                        {{ $g['actual'] !== null ? $fmtNum($g['actual']) : '—' }}
                        / {{ $fmtNum($g['target'] ?? 0) }}
                    </div>
                    <div class="cabinet-sr-goal-card__why">{{ $g['why'] ?? '' }}</div>
                </div>
            @endforeach
        </section>
    @endif

    @foreach($sections as $section)
        @php
            $key = $section['key'];
            if ($key === 'cover') {
                continue;
            }
            $enabled = array_key_exists('enabled', $section) ? !empty($section['enabled']) : true;
            $clientVisible = array_key_exists('client_visible', $section) ? !empty($section['client_visible']) : true;
            if ($isPublic && !$clientVisible) {
                continue;
            }
            if (!$isPublic && !$enabled && !isset($section['source_status'])) {
                continue;
            }
            $showDead = !$isPublic && $enabled && !$clientVisible;
            $hiddenClient = !$isPublic && !$clientVisible;
            if ($confirmedOnly && $isPublic && in_array($key, ['summary', 'work_done', 'work_plan'], true)) {
                $textField = $key === 'summary' ? $report->summary_text
                    : ($key === 'work_done' ? $report->work_done_text : $report->work_plan_text);
                if (trim((string) $textField) === '' && empty($insights) && empty($snapshot['recommendations'])) {
                    continue;
                }
            }
        @endphp

        <section class="cabinet-sr-report-block {{ $hiddenClient ? 'cabinet-sr-report-block--hidden-client' : '' }}"
                 id="sr-{{ $key }}">
            <header class="cabinet-sr-section-head">
                <h2 class="cabinet-sr-section-head__title">{{ $section['title'] }}</h2>
            </header>

            @if(!$isPublic && !$enabled)
                <p class="small text-secondary mb-0">{{ __('Section disabled in project settings') }}</p>
            @elseif($showDead)
                <p class="small text-secondary mb-0">
                    {{ __('Not connected') }} — {{ __('Hidden for client') }}.
                    @if(!empty($section['message'])) {{ $section['message'] }} @endif
                    <a href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">{{ __('Connect source') }}</a>
                </p>
            @elseif($key === 'summary')
                @if($report->summary_text)
                    <div class="cabinet-sr-prose">{!! nl2br(e($report->summary_text)) !!}</div>
                @elseif(!empty($insights))
                    <ul class="cabinet-sr-bullets">
                        @foreach($insights as $b)
                            <li>{{ $b }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
            @elseif($key === 'traffic' && is_array($traffic))
                @if(($traffic['mode'] ?? 'all') === 'search_only')
                    <p class="small text-secondary">{{ __('Search only') }}</p>
                @endif
                <div class="cabinet-sr-kpi-grid">
                    @foreach($kpiLabels as $metric => $label)
                        @continue(!$metricOn('traffic', $metric))
                        @php
                            $kpi = $traffic['kpis'][$metric] ?? null;
                            $value = $kpi['value'] ?? null;
                            $delta = $kpi['delta_pct'] ?? null;
                            if ($metric === 'bounce_rate' && $value !== null) {
                                $display = $fmtNum($value, 1) . '%';
                            } elseif ($metric === 'page_depth' && $value !== null) {
                                $display = $fmtNum($value, 2);
                            } elseif ($metric === 'avg_visit_duration' && $value !== null) {
                                $display = $fmtDur($value);
                            } elseif ($value !== null) {
                                $display = $fmtNum($value);
                            } else {
                                $display = '—';
                            }
                            $deltaClass = '';
                            if ($delta !== null) {
                                $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
                                if ($metric === 'bounce_rate') {
                                    $deltaClass = $delta < 0 ? 'is-up' : ($delta > 0 ? 'is-down' : '');
                                }
                            }
                        @endphp
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">{{ $display }}</div>
                            @if($delta !== null)
                                <div class="cabinet-sr-kpi__delta {{ $deltaClass }}">
                                    {{ $delta > 0 ? '+' : '' }}{{ $fmtNum($delta, 1) }}%
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @php
                    $trafficComment = $comments['traffic'] ?? ($traffic['auto_comment'] ?? null);
                @endphp
                @if($trafficComment && $metricOn('traffic', 'comment'))
                    <p class="cabinet-sr-comment mt-2">{{ $trafficComment }}</p>
                @endif

                @if(!empty($traffic['series_users']) && $metricOn('traffic', 'series_users'))
                    <div class="cabinet-sr-spark mt-3">
                        @php
                            $seriesUsers = $traffic['series_users'];
                            $vals = array_values($seriesUsers);
                            $dates = array_keys($seriesUsers);
                            $max = max(1, max($vals ?: [1]));
                        @endphp
                        @foreach($vals as $idx => $v)
                            <span title="{{ $dates[$idx] ?? '' }}: {{ $fmtNum($v) }}"
                                  style="height: {{ max(8, (int) round(48 * $v / $max)) }}px"></span>
                        @endforeach
                    </div>
                    <p class="small text-secondary mb-0 mt-1">{{ __('Users by day') }}</p>
                @endif

                @if(!empty($traffic['channels']) && $metricOn('traffic', 'channels'))
                    <h3 class="h6 mt-3">{{ __('Channels') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Channel') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Bounce rate') }}</th>
                                <th>{{ __('Page depth') }}</th>
                                <th>{{ __('Avg. visit duration') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['channels'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                    <td>{{ $fmtNum($row['page_depth'] ?? 0, 2) }}</td>
                                    <td>{{ $fmtDur($row['avg_visit_duration'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(!empty($traffic['channels']))
                        <div class="cabinet-sr-bars mt-2">
                            @php $maxCh = max(1, max(array_column($traffic['channels'], 'visits') ?: [1])); @endphp
                            @foreach(array_slice($traffic['channels'], 0, 6) as $row)
                                <div class="cabinet-sr-bars__row">
                                    <span>{{ $row['name'] }}</span>
                                    <i style="width: {{ max(4, (int) round(100 * ($row['visits'] ?? 0) / $maxCh)) }}%"></i>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                @if(!empty($traffic['channel_months']) && $metricOn('traffic', 'channel_months'))
                    <h3 class="h6 mt-3">{{ __('Channels by month') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Month') }}</th>
                                <th>{{ __('Top channel') }}</th>
                                <th>{{ __('Visits') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['channel_months'] as $month)
                                @php $top = $month['channels'][0] ?? null; @endphp
                                <tr>
                                    <td>{{ $month['month'] }}</td>
                                    <td>{{ $top['name'] ?? '—' }}</td>
                                    <td>{{ $top ? $fmtNum($top['visits'] ?? 0) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['sources']) && $metricOn('traffic', 'sources'))
                    <h3 class="h6 mt-3">{{ __('Traffic sources') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Source') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Users') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['sources'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['users'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @php $search = $traffic['search'] ?? null; @endphp
                @if(is_array($search) && $metricOn('traffic', 'search'))
                    <h3 class="h6 mt-3">{{ __('Search traffic') }}</h3>
                    <div class="cabinet-sr-kpi-grid">
                        @foreach(['visits' => __('Visits'), 'users' => __('Users'), 'bounce_rate' => __('Bounce rate'), 'page_depth' => __('Page depth')] as $metric => $label)
                            @php
                                $kpi = $search['kpis'][$metric] ?? null;
                                $value = $kpi['value'] ?? null;
                                $delta = $kpi['delta_pct'] ?? null;
                                if ($value === null) { $display = '—'; }
                                elseif ($metric === 'bounce_rate') { $display = $fmtNum($value, 1) . '%'; }
                                elseif ($metric === 'page_depth') { $display = $fmtNum($value, 2); }
                                else { $display = $fmtNum($value); }
                            @endphp
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                                <div class="cabinet-sr-kpi__value">{{ $display }}</div>
                                @if($delta !== null)
                                    <div class="cabinet-sr-kpi__delta {{ $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '') }}">
                                        {{ $delta > 0 ? '+' : '' }}{{ $fmtNum($delta, 1) }}%
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($search['series_visits']))
                        <div class="cabinet-sr-spark mt-2" aria-hidden="true">
                            @php
                                $vals = array_values($search['series_visits']);
                                $max = max(1, max($vals ?: [1]));
                            @endphp
                            @foreach($vals as $v)
                                <span style="height: {{ max(8, (int) round(48 * $v / $max)) }}px"></span>
                            @endforeach
                        </div>
                        <p class="small text-secondary mb-0 mt-1">{{ __('Search visits by day') }}</p>
                    @endif
                    @if(!empty($search['engines']))
                        <div class="table-responsive mt-2">
                            <table class="cabinet-sr-data-table">
                                <thead>
                                <tr>
                                    <th>{{ __('Search engine') }}</th>
                                    <th>{{ __('Visits') }}</th>
                                    <th>{{ __('Bounce rate') }}</th>
                                    <th>{{ __('Compare') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($search['engines'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                        <td>
                                            @if(isset($row['visits_delta_pct']) && $row['visits_delta_pct'] !== null)
                                                {{ $row['visits_delta_pct'] > 0 ? '+' : '' }}{{ $fmtNum($row['visits_delta_pct'], 1) }}%
                                            @elseif(isset($row['visits_prev']))
                                                {{ $fmtNum($row['visits_prev']) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if(!empty($traffic['devices']) && $metricOn('traffic', 'devices'))
                    <h3 class="h6 mt-3">{{ __('Devices') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Device') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Bounce rate') }}</th>
                                <th>{{ __('Page depth') }}</th>
                                <th>{{ __('Avg. visit duration') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['devices'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                    <td>{{ $fmtNum($row['page_depth'] ?? 0, 2) }}</td>
                                    <td>{{ $fmtDur($row['avg_visit_duration'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="cabinet-sr-bars mt-2">
                        @php $maxDev = max(1, max(array_column($traffic['devices'], 'visits') ?: [1])); @endphp
                        @foreach(array_slice($traffic['devices'], 0, 6) as $row)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $row['name'] }}</span>
                                <i style="width: {{ max(4, (int) round(100 * ($row['visits'] ?? 0) / $maxDev)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($traffic['geo']) && $metricOn('traffic', 'geo'))
                    <h3 class="h6 mt-3">{{ __('Geography') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('City') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Users') }}</th>
                                <th>{{ __('Bounce rate') }}</th>
                                <th>{{ __('Page depth') }}</th>
                                <th>{{ __('Avg. visit duration') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['geo'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['users'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                    <td>{{ $fmtNum($row['page_depth'] ?? 0, 2) }}</td>
                                    <td>{{ $fmtDur($row['avg_visit_duration'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="cabinet-sr-bars mt-2">
                        @php $maxGeo = max(1, max(array_column($traffic['geo'], 'visits') ?: [1])); @endphp
                        @foreach(array_slice($traffic['geo'], 0, 8) as $row)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $row['name'] }}</span>
                                <i style="width: {{ max(4, (int) round(100 * ($row['visits'] ?? 0) / $maxGeo)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($traffic['landings']) && $metricOn('traffic', 'landings'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('URL') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Delta') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['landings'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>
                                        @if(isset($row['visits_delta_pct']) && $row['visits_delta_pct'] !== null)
                                            {{ $row['visits_delta_pct'] > 0 ? '+' : '' }}{{ $fmtNum($row['visits_delta_pct'], 1) }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['landings_search']) && $metricOn('traffic', 'landings_search'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from search') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('URL') }}</th>
                                <th>{{ __('Visits') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['landings_search'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['landings_social']) && $metricOn('traffic', 'landings_social'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from social') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('URL') }}</th>
                                <th>{{ __('Visits') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['landings_social'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($key === 'positions' && is_array($positions))
                @php
                    $sum = $positions['summary'] ?? [];
                    $dyn = $positions['dynamics'] ?? [];
                @endphp
                @if($metricOn('positions', 'summary'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach(['top3' => 'TOP-3', 'top10' => 'TOP-10', 'top30' => 'TOP-30', 'top100' => 'TOP-100'] as $k => $label)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">{{ $sum[$k] ?? '—' }}</div>
                            @if(isset($sum['diff_' . $k]) && $sum['diff_' . $k] !== null && $sum['diff_' . $k] !== '')
                                <div class="cabinet-sr-kpi__delta">{{ $sum['diff_' . $k] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($dyn['pairs']) && $metricOn('positions', 'dynamics'))
                    <div class="cabinet-sr-kpi-grid mt-2">
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ __('Improved') }}</div>
                            <div class="cabinet-sr-kpi__value is-up">{{ (int) ($dyn['improved'] ?? 0) }}</div>
                        </div>
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ __('Unchanged') }}</div>
                            <div class="cabinet-sr-kpi__value">{{ (int) ($dyn['unchanged'] ?? 0) }}</div>
                        </div>
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ __('Worsened') }}</div>
                            <div class="cabinet-sr-kpi__value is-down">{{ (int) ($dyn['worsened'] ?? 0) }}</div>
                        </div>
                    </div>
                @endif
                @if(!empty($positions['top_baskets']) && $metricOn('positions', 'top_baskets'))
                    <h3 class="h6 mt-3">{{ __('TOP baskets') }}</h3>
                    <div class="cabinet-sr-bars">
                        @php $maxB = max(1, max(array_column($positions['top_baskets'], 'value') ?: [1])); @endphp
                        @foreach($positions['top_baskets'] as $b)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $b['label'] }} ({{ $b['value'] }}@if(!empty($b['diff'])) {{ $b['diff'] }}@endif)</span>
                                <i style="width: {{ max(4, (int) round(100 * ($b['value'] ?? 0) / $maxB)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if(!empty($positions['visibility_by_engine']) && $metricOn('positions', 'visibility_by_engine'))
                    <h3 class="h6 mt-3">{{ __('Visibility by search engine') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Region') }}</th>
                                <th>TOP-10 %</th>
                                <th>TOP-10</th>
                                <th>{{ __('Queries') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['visibility_by_engine'] as $row)
                                <tr>
                                    <td>{{ $row['engine'] }}</td>
                                    <td>{{ $row['region'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['pct'] ?? 0, 1) }}%</td>
                                    <td>{{ $row['top10'] ?? '—' }}</td>
                                    <td>{{ $row['words'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['visibility_series']) && $metricOn('positions', 'visibility_series'))
                    <h3 class="h6 mt-3">{{ __('Visibility TOP-10') }}</h3>
                    <div class="cabinet-sr-spark" aria-hidden="true">
                        @php
                            $vals = array_column($positions['visibility_series'], 'pct');
                            $max = max(1, max($vals ?: [1]));
                        @endphp
                        @foreach($positions['visibility_series'] as $point)
                            <span title="{{ $point['date'] }}: {{ $point['pct'] }}%"
                                  style="height: {{ max(8, (int) round(48 * $point['pct'] / $max)) }}px"></span>
                        @endforeach
                    </div>
                    <p class="small text-secondary mb-0 mt-1">
                        {{ __('Share of queries in TOP-10 by day') }}
                        @php $last = end($positions['visibility_series']); @endphp
                        @if($last)
                            · {{ __('Now') }}: {{ $last['pct'] }}% ({{ $last['top10'] }}/{{ $last['words'] }})
                        @endif
                    </p>
                @endif
                @if(
                    ($metricOn('positions', 'phrases_improved') && !empty($positions['phrases']['improved']))
                    || ($metricOn('positions', 'phrases_worsened') && !empty($positions['phrases']['worsened']))
                )
                    @foreach(['improved' => __('Improved queries'), 'worsened' => __('Worsened queries')] as $bucket => $title)
                        @continue($bucket === 'improved' && !$metricOn('positions', 'phrases_improved'))
                        @continue($bucket === 'worsened' && !$metricOn('positions', 'phrases_worsened'))
                        @if(!empty($positions['phrases'][$bucket]))
                            <h3 class="h6 mt-3">{{ $title }}</h3>
                            <div class="table-responsive">
                                <table class="cabinet-sr-data-table">
                                    <thead>
                                    <tr>
                                        <th>{{ __('Query') }}</th>
                                        <th>{{ __('Search engine') }}</th>
                                        <th>{{ __('Was') }}</th>
                                        <th>{{ __('Became') }}</th>
                                        <th>{{ __('Landing URL') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($positions['phrases'][$bucket] as $row)
                                        <tr>
                                            <td>{{ $row['query'] ?? '—' }}</td>
                                            <td>{{ $row['engine'] ?? '—' }}</td>
                                            <td>{{ $row['pos_from'] ?? '—' }}</td>
                                            <td class="{{ $bucket === 'improved' ? 'text-success' : 'text-danger' }}">{{ $row['pos_to'] ?? '—' }}</td>
                                            <td class="cabinet-sr-url">{{ !empty($row['url']) ? $row['url'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                @endif
                @if(!empty($positions['by_engine']) && $metricOn('positions', 'by_engine'))
                    <div class="table-responsive mt-3">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Region') }}</th>
                                <th>{{ __('Queries') }}</th>
                                <th>TOP-10</th>
                                <th>TOP-100</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['by_engine'] as $row)
                                <tr>
                                    <td>{{ $row['engine'] }}</td>
                                    <td>{{ $row['region'] ?? '—' }}</td>
                                    <td>{{ $row['words'] ?? '—' }}</td>
                                    <td>{{ $row['top10'] ?? '—' }}</td>
                                    <td>{{ $row['top100'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['quick_wins']) && $metricOn('positions', 'quick_wins'))
                    <h3 class="h6 mt-3">{{ __('Quick wins') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Query') }}</th>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Became') }}</th>
                                <th>{{ __('Landing URL') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['quick_wins'] as $row)
                                <tr>
                                    <td>{{ $row['query'] ?? '—' }}</td>
                                    <td>{{ $row['engine'] ?? '—' }}</td>
                                    <td>{{ $row['pos_to'] ?? ($row['position'] ?? '—') }}</td>
                                    <td class="cabinet-sr-url">{{ !empty($row['url']) ? $row['url'] : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['risk']) && $metricOn('positions', 'risk'))
                    <h3 class="h6 mt-3">{{ __('Risk list') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Query') }}</th>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Was') }}</th>
                                <th>{{ __('Became') }}</th>
                                <th>{{ __('Landing URL') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['risk'] as $row)
                                <tr>
                                    <td>{{ $row['query'] ?? '—' }}</td>
                                    <td>{{ $row['engine'] ?? '—' }}</td>
                                    <td>{{ $row['pos_from'] ?? ($row['was'] ?? '—') }}</td>
                                    <td class="text-danger">{{ $row['pos_to'] ?? ($row['now'] ?? '—') }}</td>
                                    <td class="cabinet-sr-url">{{ !empty($row['url']) ? $row['url'] : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['groups']) && $metricOn('positions', 'groups'))
                    <h3 class="h6 mt-3">{{ __('Keyword groups') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Group') }}</th>
                                <th>{{ __('Queries') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['groups'] as $g)
                                <tr>
                                    <td>{{ $g['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($g['words'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['competitors']['urls']) && $metricOn('positions', 'competitors'))
                    <h3 class="h6 mt-3">{{ __('Competitors from monitoring') }}</h3>
                    <ul class="cabinet-sr-bullets">
                        @foreach($positions['competitors']['urls'] as $url)
                            <li>{{ $url }}</li>
                        @endforeach
                    </ul>
                    <p class="small text-secondary mb-0">
                        {{ __('Competitors tracked') }}: {{ (int) ($positions['competitors']['count'] ?? 0) }}
                    </p>
                @endif
                @if(!empty($positions['note']))
                    <p class="small text-secondary mb-0 mt-2">{{ $positions['note'] }}</p>
                @endif
                @if(!empty($comments['positions']))
                    <p class="cabinet-sr-comment mt-2">{{ $comments['positions'] }}</p>
                @endif
            @elseif($key === 'conversions' && is_array($conversions))
                @if(!empty($conversions['goals']) && $metricOn('conversions', 'goals'))
                    <h3 class="h6">{{ __('Conversions by goals') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                                <th>{{ __('Cost per conversion') }}</th>
                                <th>{{ __('Delta') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['goals'] as $goal)
                                @php
                                    $reaches = $goal['reaches']['value'] ?? null;
                                    $rate = $goal['conversion_rate']['value'] ?? null;
                                    $delta = $goal['reaches']['delta_pct'] ?? null;
                                    $cpa = $goal['cost_per_conversion'] ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $goal['name'] ?? ('#' . ($goal['id'] ?? '')) }}</td>
                                    <td>{{ $reaches !== null ? $fmtNum($reaches) : '—' }}</td>
                                    <td>{{ $rate !== null ? $fmtNum($rate, 2) . '%' : '—' }}</td>
                                    <td>{{ $cpa !== null ? $fmtNum($cpa, 2) : '—' }}</td>
                                    <td>
                                        @if($delta !== null)
                                            <span class="{{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : '') }}">
                                                {{ $delta > 0 ? '+' : '' }}{{ $fmtNum($delta, 1) }}%
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-secondary">{{ __('Cost per conversion needs ads spend') }}</p>
                @endif

                @if(!empty($conversions['channels_by_goal']) && $metricOn('conversions', 'channels_by_goal'))
                    @foreach($conversions['channels_by_goal'] as $goalId => $channelRows)
                        @if(!empty($channelRows))
                            @php
                                $goalTitle = '#' . $goalId;
                                foreach (($conversions['goals'] ?? []) as $g) {
                                    if ((int) ($g['id'] ?? 0) === (int) $goalId) {
                                        $goalTitle = (string) ($g['name'] ?? $goalTitle);
                                        break;
                                    }
                                }
                            @endphp
                            <h3 class="h6 mt-3">{{ __('Channels') }} · {{ $goalTitle }}</h3>
                            <div class="table-responsive">
                                <table class="cabinet-sr-data-table">
                                    <thead>
                                    <tr>
                                        <th>{{ __('Channel') }}</th>
                                        <th>{{ __('Goal reaches') }}</th>
                                        <th>{{ __('Conversion rate') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($channelRows as $row)
                                        <tr>
                                            <td>{{ $row['name'] ?? '—' }}</td>
                                            <td>{{ $fmtNum($row['reaches'] ?? 0) }}</td>
                                            <td>{{ $fmtNum($row['conversion_rate'] ?? 0, 2) }}%</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if(count($channelRows) > 0)
                                <div class="cabinet-sr-bars mt-2">
                                    @php $maxCh = max(1, max(array_column($channelRows, 'reaches') ?: [1])); @endphp
                                    @foreach(array_slice($channelRows, 0, 6) as $row)
                                        <div class="cabinet-sr-bars__row">
                                            <span>{{ $row['name'] ?? '—' }}</span>
                                            <i style="width: {{ max(4, (int) round(100 * ($row['reaches'] ?? 0) / $maxCh)) }}%"></i>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endif

                @if(!empty($conversions['search_goals']) && $metricOn('conversions', 'search_goals'))
                    <h3 class="h6 mt-3">{{ __('Conversions from search') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['search_goals'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($conversions['ad_goals']) && $metricOn('conversions', 'ad_goals'))
                    <h3 class="h6 mt-3">{{ __('Conversions from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['ad_goals'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($conversions['social_goals']) && $metricOn('conversions', 'social_goals'))
                    <h3 class="h6 mt-3">{{ __('Conversions from social') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['social_goals'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($comments['conversions']))
                    <p class="cabinet-sr-comment mt-2">{{ $comments['conversions'] }}</p>
                @elseif(!empty($conversions['comment']))
                    <p class="cabinet-sr-comment mt-2">{{ $conversions['comment'] }}</p>
                @endif
            @elseif($key === 'ecommerce')
                @php $ecom = is_array($snapshot['ecommerce'] ?? null) ? $snapshot['ecommerce'] : null; @endphp
                @if(is_array($ecom) && !empty($ecom['available']))
                    <div class="cabinet-sr-kpi-grid">
                        @foreach([
                            'users' => __('Users'),
                            'purchases' => __('Purchases'),
                            'revenue' => __('Revenue'),
                            'cr' => __('CR'),
                            'rpv' => 'RPV',
                            'aov' => __('Avg. check'),
                        ] as $ek => $elabel)
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $elabel }}</div>
                                <div class="cabinet-sr-kpi__value">
                                    @if(in_array($ek, ['cr'], true))
                                        {{ $fmtNum($ecom[$ek] ?? 0, 2) }}%
                                    @elseif(in_array($ek, ['revenue', 'rpv', 'aov'], true))
                                        {{ $fmtNum($ecom[$ek] ?? 0, 2) }}
                                    @else
                                        {{ $fmtNum($ecom[$ek] ?? 0) }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($ecom['by_source']))
                        <h3 class="h6 mt-3">{{ __('Revenue by traffic source') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Revenue') }}</th></tr></thead>
                                <tbody>
                                @foreach($ecom['by_source'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($row['purchases'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="cabinet-sr-bars mt-2">
                            @php $maxRev = max(1, max(array_column($ecom['by_source'], 'revenue') ?: [1])); @endphp
                            @foreach(array_slice($ecom['by_source'], 0, 8) as $row)
                                <div class="cabinet-sr-bars__row">
                                    <span>{{ $row['name'] }}</span>
                                    <i style="width: {{ max(4, (int) round(100 * ($row['revenue'] ?? 0) / $maxRev)) }}%"></i>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($ecom['categories']))
                        <h3 class="h6 mt-3">{{ __('Popular categories') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Revenue') }}</th></tr></thead>
                                <tbody>
                                @foreach($ecom['categories'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($row['purchases'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if(!empty($ecom['products']))
                        <h3 class="h6 mt-3">{{ __('Popular products') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Revenue') }}</th></tr></thead>
                                <tbody>
                                @foreach($ecom['products'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($row['purchases'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <p class="small text-secondary mb-0">
                        {{ is_array($ecom) ? ($ecom['note'] ?? __('Ecommerce metrics require Metrika ecommerce tracking')) : __('Not connected') }}
                    </p>
                @endif
            @elseif($key === 'direct' && is_array($snapshot['direct'] ?? null))
                @php $direct = $snapshot['direct']; @endphp
                @if(!empty($direct['note']))
                    <p class="small text-secondary">{{ $direct['note'] }}</p>
                @endif
                @if($metricOn('direct', 'kpis'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach($kpiLabels as $metric => $label)
                        @php
                            $kpi = $direct['kpis'][$metric] ?? null;
                            $value = $kpi['value'] ?? null;
                            $delta = $kpi['delta_pct'] ?? null;
                        @endphp
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if($metric === 'bounce_rate' && $value !== null)
                                    {{ $fmtNum($value, 1) }}%
                                @elseif($value !== null)
                                    {{ $fmtNum($value, in_array($metric, ['page_depth'], true) ? 2 : 0) }}
                                @else
                                    —
                                @endif
                            </div>
                            @if($delta !== null)
                                <div class="cabinet-sr-kpi__delta">{{ $delta > 0 ? '+' : '' }}{{ $fmtNum($delta, 1) }}%</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($direct['spend']) && $metricOn('direct', 'spend') && (isset($direct['spend']['cost']) || isset($direct['spend']['clicks'])))
                    <div class="cabinet-sr-kpi-grid mt-2">
                        @foreach(['clicks' => __('Clicks'), 'cost' => __('Ad spend'), 'cpc' => 'CPC', 'ctr' => 'CTR'] as $sk => $sl)
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                                <div class="cabinet-sr-kpi__value">
                                    @if(($direct['spend'][$sk] ?? null) !== null)
                                        {{ $fmtNum($direct['spend'][$sk], in_array($sk, ['cost', 'cpc', 'ctr'], true) ? 2 : 0) }}{{ $sk === 'ctr' ? '%' : '' }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if(!empty($direct['series_visits']) && $metricOn('direct', 'series_visits'))
                    <div class="cabinet-sr-spark mt-3">
                        @php
                            $vals = array_values($direct['series_visits']);
                            $dates = array_keys($direct['series_visits']);
                            $max = max(1, max($vals ?: [1]));
                        @endphp
                        @foreach($vals as $idx => $v)
                            <span title="{{ $dates[$idx] ?? '' }}: {{ $fmtNum($v) }}"
                                  style="height: {{ max(8, (int) round(48 * $v / $max)) }}px"></span>
                        @endforeach
                    </div>
                    <p class="small text-secondary mb-0 mt-1">{{ __('Ad visits by day') }}</p>
                @endif
                @if(!empty($direct['engines']) && $metricOn('direct', 'engines'))
                    <h3 class="h6 mt-3">{{ __('Ad engines') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Engine') }}</th><th>{{ __('Visits') }}</th><th>{{ __('Bounce rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['engines'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['campaigns']) && $metricOn('direct', 'campaigns'))
                    <h3 class="h6 mt-3">{{ __('Campaigns') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Visits') }}</th><th>{{ __('Bounce rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['campaigns'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['platforms']) && $metricOn('direct', 'platforms'))
                    <h3 class="h6 mt-3">{{ __('Ad platforms') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Platform') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['platforms'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['phrases']) && $metricOn('direct', 'phrases'))
                    <h3 class="h6 mt-3">{{ __('Search phrases') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Query') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['phrases'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['landings']) && $metricOn('direct', 'landings'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['landings'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['conversions']) && $metricOn('direct', 'conversions'))
                    <h3 class="h6 mt-3">{{ __('Conversions from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Goal') }}</th><th>{{ __('Goal reaches') }}</th><th>{{ __('Conversion rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['conversions'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['fix']) && $metricOn('direct', 'fix'))
                    <h3 class="h6 mt-3">{{ __('What to fix') }}</h3>
                    <ul class="cabinet-sr-bullets">
                        @foreach($direct['fix'] as $hint)
                            <li>{{ $hint }}</li>
                        @endforeach
                    </ul>
                @endif
            @elseif($key === 'calls' && is_array($snapshot['calls'] ?? null))
                @php $calls = $snapshot['calls']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Calls total') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtNum($calls['total'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('First calls') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtNum($calls['first'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Missed calls') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtNum($calls['missed'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Talk avg') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtDur($calls['talk_avg'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Hold avg') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtDur($calls['hold_avg'] ?? 0) }}</div></div>
                </div>
                @if(!empty($calls['by_channel']))
                    <h3 class="h6 mt-3">{{ __('Calls by channel') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Calls total') }}</th><th>{{ __('Missed calls') }}</th></tr></thead>
                            <tbody>
                            @foreach($calls['by_channel'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['calls'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['missed'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="cabinet-sr-bars mt-2">
                        @php $maxCalls = max(1, max(array_column($calls['by_channel'], 'calls') ?: [1])); @endphp
                        @foreach(array_slice($calls['by_channel'], 0, 8) as $row)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $row['name'] }}</span>
                                <i style="width: {{ max(4, (int) round(100 * ($row['calls'] ?? 0) / $maxCalls)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif
            @elseif(in_array($key, ['gsc', 'webmaster'], true) && is_array($snapshot[$key] ?? null))
                @php $sc = $snapshot[$key]; @endphp
                @if(!empty($sc['note']))
                    <p class="small text-secondary">{{ $sc['note'] }}</p>
                @endif
                @if($metricOn($key, 'kpis'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach(['clicks' => __('Clicks'), 'impressions' => __('Impressions'), 'ctr' => 'CTR', 'position' => __('Avg. position')] as $sk => $sl)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if(($sc['kpis'][$sk] ?? null) !== null)
                                    {{ in_array($sk, ['ctr', 'position'], true) ? $fmtNum($sc['kpis'][$sk], 2) : $fmtNum($sc['kpis'][$sk]) }}{{ $sk === 'ctr' ? '%' : '' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($sc['queries']) && $metricOn($key, 'queries'))
                    <h3 class="h6 mt-3">{{ __('Top queries') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Query') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Impressions') }}</th><th>CTR</th><th>{{ __('Avg. position') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($sc['queries'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                    <td>{{ $row['ctr'] !== null ? $fmtNum($row['ctr'], 2) . '%' : '—' }}</td>
                                    <td>{{ $row['position'] !== null ? $fmtNum($row['position'], 1) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($sc['pages']) && $metricOn($key, 'pages'))
                    <h3 class="h6 mt-3">{{ __('Top pages') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Impressions') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($sc['pages'], 0, 25) as $row)
                                <tr>
                                    <td class="cabinet-sr-url">{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($key === 'google_ads' && is_array($snapshot['google_ads'] ?? null))
                @php $gads = $snapshot['google_ads']; @endphp
                @if(!empty($gads['note']))
                    <p class="small text-secondary">{{ $gads['note'] }}</p>
                @endif
                @if($metricOn('google_ads', 'kpis'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach($kpiLabels as $metric => $label)
                        @php $kpi = $gads['kpis'][$metric] ?? null; $value = $kpi['value'] ?? null; @endphp
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if($metric === 'bounce_rate' && $value !== null)
                                    {{ $fmtNum($value, 1) }}%
                                @elseif($value !== null)
                                    {{ $fmtNum($value, $metric === 'page_depth' ? 2 : 0) }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($gads['campaigns']) && $metricOn('google_ads', 'campaigns'))
                    <h3 class="h6 mt-3">{{ __('Campaigns') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Visits') }}</th><th>{{ __('Users') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($gads['campaigns'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['users'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($gads['landings']) && $metricOn('google_ads', 'landings'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($gads['landings'] as $row)
                                <tr><td class="cabinet-sr-url">{{ $row['name'] }}</td><td>{{ $fmtNum($row['visits'] ?? 0) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($gads['phrases']) && $metricOn('google_ads', 'phrases'))
                    <h3 class="h6 mt-3">{{ __('Search phrases') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Query') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($gads['phrases'] as $row)
                                <tr><td>{{ $row['name'] ?? '—' }}</td><td>{{ $fmtNum($row['visits'] ?? 0) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($gads['conversions']) && $metricOn('google_ads', 'conversions'))
                    <h3 class="h6 mt-3">{{ __('Conversions from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Goal') }}</th><th>{{ __('Goal reaches') }}</th><th>{{ __('Conversion rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($gads['conversions'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif(in_array($key, ['vk_ads', 'meta_ads'], true) && is_array($snapshot[$key] ?? null))
                @php $ads = $snapshot[$key]; $ak = $ads['kpis'] ?? []; @endphp
                @if(!empty($ads['note']))
                    <p class="small text-secondary">{{ $ads['note'] }}</p>
                @endif
                <div class="cabinet-sr-kpi-grid">
                    @foreach([
                        'reach' => __('Reach'),
                        'impressions' => __('Impressions'),
                        'clicks' => __('Clicks'),
                        'ctr' => 'CTR',
                        'cpc' => 'CPC',
                        'cpm' => 'CPM',
                        'spend' => __('Spend'),
                    ] as $sk => $sl)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if(($ak[$sk] ?? null) !== null)
                                    {{ $fmtNum($ak[$sk], in_array($sk, ['ctr', 'cpc', 'cpm'], true) ? 2 : 0) }}{{ $sk === 'ctr' ? '%' : '' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($ads['campaigns']))
                    <h3 class="h6 mt-3">{{ __('Campaigns') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Impressions') }}</th><th>{{ __('Clicks') }}</th><th>CTR</th><th>{{ __('Spend') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($ads['campaigns'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ ($row['ctr'] ?? null) !== null ? $fmtNum($row['ctr'], 2) . '%' : '—' }}</td>
                                    <td>{{ $fmtNum($row['spend'] ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($ads['ads']))
                    <h3 class="h6 mt-3">{{ __('Ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Ad') }}</th><th>{{ __('Impressions') }}</th><th>{{ __('Clicks') }}</th><th>CTR</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($ads['ads'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ ($row['ctr'] ?? null) !== null ? $fmtNum($row['ctr'], 2) . '%' : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($ads['demography']))
                    <h3 class="h6 mt-3">{{ __('Demography') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Segment') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Impressions') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($ads['demography'], 0, 20) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ ($row['impressions'] ?? null) !== null ? $fmtNum($row['impressions']) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($key === 'vk_smm' && is_array($snapshot['vk_smm'] ?? null))
                @php $smm = $snapshot['vk_smm']; $skp = $smm['kpis'] ?? []; @endphp
                @if(!empty($smm['note']))
                    <p class="small text-secondary">{{ $smm['note'] }}</p>
                @endif
                <div class="cabinet-sr-kpi-grid">
                    @foreach([
                        'subscribers' => __('Subscribers'),
                        'reach' => __('Reach'),
                        'impressions' => __('Views'),
                        'visitors' => __('Visitors'),
                        'likes' => __('Likes'),
                        'comments' => __('Comments'),
                        'shares' => __('Shares'),
                        'posts' => __('Posts'),
                        'er' => 'ER',
                    ] as $sk => $sl)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if(($skp[$sk] ?? null) !== null)
                                    {{ $fmtNum($skp[$sk], $sk === 'er' ? 2 : 0) }}{{ $sk === 'er' ? '%' : '' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($smm['dynamics']))
                    <h3 class="h6 mt-3">{{ __('Subscribers dynamics') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Subscribers') }}</th><th>{{ __('Reach') }}</th><th>{{ __('Views') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['dynamics'], 0, 31) as $row)
                                <tr>
                                    <td>{{ $row['date'] ?? '—' }}</td>
                                    <td>{{ ($row['subscribers'] ?? null) !== null ? $fmtNum($row['subscribers']) : '—' }}</td>
                                    <td>{{ $fmtNum($row['reach'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['views'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($smm['engagement']))
                    <h3 class="h6 mt-3">{{ __('Engagement') }}</h3>
                    <p class="small mb-0">
                        {{ __('Likes') }}: {{ $fmtNum($smm['engagement']['likes'] ?? 0) }} ·
                        {{ __('Comments') }}: {{ $fmtNum($smm['engagement']['comments'] ?? 0) }} ·
                        {{ __('Shares') }}: {{ $fmtNum($smm['engagement']['shares'] ?? 0) }} ·
                        ER: {{ ($smm['engagement']['er'] ?? null) !== null ? $fmtNum($smm['engagement']['er'], 2) . '%' : '—' }}
                    </p>
                @endif
                @if(!empty($smm['top_posts']))
                    <h3 class="h6 mt-3">{{ __('Top posts') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Post') }}</th><th>{{ __('Likes') }}</th><th>{{ __('Comments') }}</th><th>{{ __('Shares') }}</th><th>{{ __('Views') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['top_posts'], 0, 15) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['likes'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['comments'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['shares'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['views'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($smm['demography']))
                    <h3 class="h6 mt-3">{{ __('Audience demography') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Segment') }}</th><th>{{ __('Count') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['demography'], 0, 20) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($smm['post_stats']) && empty($smm['top_posts']))
                    <h3 class="h6 mt-3">{{ __('Post stats') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Post') }}</th><th>{{ __('Likes') }}</th><th>{{ __('Comments') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['post_stats'], 0, 20) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['likes'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['comments'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif(in_array($key, ['gsc', 'webmaster', 'direct', 'google_ads', 'vk_ads', 'meta_ads', 'vk_smm', 'calls'], true))
                <p class="small text-secondary mb-0">
                    {{ $section['message'] ?? __('Not connected') }}
                    @if(!$isPublic)
                        · <a href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">{{ __('Connect source') }}</a>
                    @endif
                </p>
                @if(!$isPublic && !empty($comments[$key]))
                    <p class="cabinet-sr-comment mt-2">{{ $comments[$key] }}</p>
                @endif
            @elseif($key === 'work_done')
                @if($report->work_done_text)
                    <div class="cabinet-sr-prose">{!! nl2br(e($report->work_done_text)) !!}</div>
                @else
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
            @elseif($key === 'work_plan')
                @if($report->work_plan_text)
                    <div class="cabinet-sr-prose">{!! nl2br(e($report->work_plan_text)) !!}</div>
                @else
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
            @elseif($key === 'insights')
                @php $recs = is_array($snapshot['recommendations'] ?? null) ? $snapshot['recommendations'] : []; @endphp
                @if(!empty($comments['recommendations']))
                    <div class="cabinet-sr-prose">{!! nl2br(e($comments['recommendations'])) !!}</div>
                @elseif($recs !== [])
                    <ul class="cabinet-sr-bullets">
                        @foreach($recs as $r)
                            <li><strong>{{ $r['priority'] ?? 'P3' }}</strong> — {{ $r['text'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @elseif(!empty($insights))
                    <ul class="cabinet-sr-bullets">
                        @foreach($insights as $b)
                            <li>{{ $b }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
            @elseif($key === 'kpi_goals')
                @if(!empty($kpiGoalsEval))
                    <div class="cabinet-sr-goals-strip cabinet-sr-goals-strip--section">
                        @foreach($kpiGoalsEval as $g)
                            <div class="cabinet-sr-goal-card cabinet-sr-goal-card--{{ $g['tone'] ?? 'yellow' }}">
                                <div class="cabinet-sr-goal-card__label">{{ $g['label'] }}</div>
                                <div class="cabinet-sr-goal-card__pct">
                                    {{ $g['pct'] !== null ? $fmtNum($g['pct'], 1) . '%' : '—' }}
                                </div>
                                <div class="cabinet-sr-goal-card__meta">
                                    {{ $g['actual'] !== null ? $fmtNum($g['actual']) : '—' }}
                                    / {{ $fmtNum($g['target'] ?? 0) }}
                                </div>
                                <div class="cabinet-sr-goal-card__why">{{ $g['why'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="small text-secondary mb-0">
                        {{ __('No KPI goals') }}
                        @if(!$isPublic)
                            · <a href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">{{ __('Settings') }}</a>
                        @endif
                    </p>
                @endif
            @elseif($key === 'titlo_audit' && !empty($snapshot['titlo_audit']))
                @php $a = $snapshot['titlo_audit']; $b = $a['buckets'] ?? []; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Critical') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($b['critical'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Other') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($b['other'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Warnings') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($b['warning'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">Info</div><div class="cabinet-sr-kpi__value">{{ (int)($b['info'] ?? 0) }}</div></div>
                </div>
                @if(!empty($a['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $a['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'titlo_checklist' && !empty($snapshot['titlo_checklist']))
                @php $c = $snapshot['titlo_checklist']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Closed in period') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($c['closed_in_period'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Overdue') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($c['overdue'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Progress') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($c['progress_done'] ?? 0) }}/{{ (int)($c['progress_total'] ?? 0) }}</div></div>
                </div>
                @if(!empty($c['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $c['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'titlo_relevance' && !empty($snapshot['titlo_relevance']))
                @php $rel = $snapshot['titlo_relevance']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Analyses') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($rel['count_checks'] ?? $rel['analyses'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Avg. score') }}</div><div class="cabinet-sr-kpi__value">{{ ($rel['avg_points'] ?? $rel['avg_score'] ?? null) !== null ? $fmtNum($rel['avg_points'] ?? $rel['avg_score'], 1) : '—' }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Avg. position') }}</div><div class="cabinet-sr-kpi__value">{{ ($rel['avg_position'] ?? null) !== null ? $fmtNum($rel['avg_position'], 1) : '—' }}</div></div>
                </div>
                @if(!empty($rel['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $rel['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'titlo_uptime' && !empty($snapshot['titlo_uptime']))
                @php $u = $snapshot['titlo_uptime']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Uptime') }}</div><div class="cabinet-sr-kpi__value">{{ ($u['uptime_percent'] ?? null) !== null ? $fmtNum($u['uptime_percent'], 2) . '%' : '—' }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Incidents') }}</div><div class="cabinet-sr-kpi__value">{{ !empty($u['broken']) ? __('Yes') : __('No') }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Domain days left') }}</div><div class="cabinet-sr-kpi__value">{{ ($u['domain_days_left'] ?? null) !== null ? (int)$u['domain_days_left'] : '—' }}</div></div>
                </div>
                @if(!empty($u['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $u['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @else
                <p class="small text-secondary mb-0">{{ __('Placeholder: data will be collected on generate') }}</p>
            @endif

            @if($isPublic && $enabled && $clientVisible)
                <div class="cabinet-sr-react" data-sr-react-section="{{ $key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-sr-react="like">👍</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-sr-react="question">{{ __('Question') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-sr-react="clarify">{{ __('Need clarification') }}</button>
                </div>
            @endif
        </section>
    @endforeach
    </div>
</div>

<script>
(function () {
    var toc = document.querySelector('[data-sr-toc]');
    var bar = document.querySelector('[data-sr-toc-bar]');
    var links = toc ? Array.prototype.slice.call(toc.querySelectorAll('[data-sr-toc-link]')) : [];
    var sections = links.map(function (a) {
        return document.getElementById(a.getAttribute('data-sr-toc-link') || '');
    }).filter(Boolean);
    var activeId = '';

    function probeOffset() {
        // Sticky demo banner / public header — activate when section enters upper third.
        var banner = document.querySelector('.cabinet-sr-demo-banner');
        var h = banner ? banner.getBoundingClientRect().height : 0;
        var viewH = window.innerHeight || document.documentElement.clientHeight || 0;
        return Math.max(Math.round(h + 24), Math.round(viewH * 0.32));
    }

    function setActive(id) {
        if (!id || id === activeId) return;
        activeId = id;
        var activeLink = null;
        links.forEach(function (a) {
            var on = a.getAttribute('data-sr-toc-link') === id;
            a.classList.toggle('is-active', on);
            if (on) activeLink = a;
        });
        // Sticky TOC scrolls itself — keep the active item in view.
        if (activeLink && toc && typeof activeLink.scrollIntoView === 'function') {
            var linkBox = activeLink.getBoundingClientRect();
            var tocBox = toc.getBoundingClientRect();
            if (linkBox.top < tocBox.top + 48 || linkBox.bottom > tocBox.bottom - 8) {
                activeLink.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function onScroll() {
        var viewH = window.innerHeight || document.documentElement.clientHeight || 0;
        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
        var docH = Math.max(
            document.body ? document.body.scrollHeight : 0,
            document.documentElement.scrollHeight || 0
        );
        if (bar) {
            var max = Math.max(1, docH - viewH);
            bar.style.width = Math.min(100, Math.round(y / max * 100)) + '%';
        }
        if (!sections.length) return;

        // Trailing sections often sit below max scroll (short blocks + footer) —
        // near the bottom widen the probe so they can become active.
        var probe = probeOffset();
        if ((y + viewH) >= (docH - 80)) {
            probe = Math.max(probe, Math.round(viewH * 0.78));
        }

        var current = sections[0];
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].getBoundingClientRect().top <= probe) {
                current = sections[i];
            } else {
                break;
            }
        }
        if (current && current.id) setActive(current.id);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    onScroll();

    // Jump like SEO Checklist stage-nav (open target, smooth scroll)
    if (toc) {
        toc.querySelectorAll('[data-sr-section-jump]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var key = a.getAttribute('data-sr-section-jump');
                var el = key ? document.getElementById('sr-' + key) : null;
                if (!el) return;
                e.preventDefault();
                setActive('sr-' + key);
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (history && history.replaceState) {
                    history.replaceState(null, '', '#sr-' + key);
                }
            });
        });
    }

    var report = document.querySelector('[data-sr-report]');
    var toggle = document.querySelector('[data-sr-compare-toggle]');
    if (report && toggle) {
        toggle.addEventListener('change', function () {
            report.setAttribute('data-sr-hide-compare', toggle.checked ? '0' : '1');
        });
    }
    document.querySelectorAll('table[data-sr-sortable]').forEach(function (table) {
        table.querySelectorAll('thead th').forEach(function (th, idx) {
            th.addEventListener('click', function () {
                var tbody = table.tBodies[0];
                if (!tbody) return;
                var rows = Array.prototype.slice.call(tbody.rows);
                var asc = th.getAttribute('data-sort') !== 'asc';
                rows.sort(function (a, b) {
                    var av = (a.cells[idx] ? a.cells[idx].innerText : '').trim();
                    var bv = (b.cells[idx] ? b.cells[idx].innerText : '').trim();
                    var an = parseFloat(av.replace(/\s/g, '').replace(',', '.'));
                    var bn = parseFloat(bv.replace(/\s/g, '').replace(',', '.'));
                    if (!isNaN(an) && !isNaN(bn)) {
                        return asc ? an - bn : bn - an;
                    }
                    return asc ? av.localeCompare(bv, 'ru') : bv.localeCompare(av, 'ru');
                });
                th.setAttribute('data-sort', asc ? 'asc' : 'desc');
                rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    });
})();
</script>
