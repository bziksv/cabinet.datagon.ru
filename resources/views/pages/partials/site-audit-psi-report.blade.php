{{-- Rich PageSpeed Insights report (lab + categories + opportunities) --}}
@php
    use App\Services\SiteAudit\SiteAuditPsiMetrics;

    $psiRows = $rows ?? collect();
    $psiSummary = SiteAuditPsiMetrics::summarize($psiRows);
    $avgBand = SiteAuditPsiMetrics::scoreBand($psiSummary['avg']);
    $strategyLabel = ($code ?? '') === 'psi_desktop' ? 'Desktop' : 'Mobile';
    $psiGoogleBase = 'https://pagespeed.web.dev/analysis?url=';
    $showActions = !empty($canNote) || !empty($canIgnore);
@endphp

@if($psiRows->isEmpty())
    <div class="text-muted px-3 py-3">
        @if(!empty($probeSkipped))
            Находок нет — проверка ещё не запускалась. Нажмите «Запустить» выше.
        @else
            Находок нет — проверка выполнена, замечаний по этому отчёту нет.
        @endif
    </div>
@else
    <div class="cabinet-sa-psi">
        <div class="cabinet-sa-psi-hero">
            <div class="cabinet-sa-psi-hero__score cabinet-sa-psi-band--{{ $avgBand }}">
                <div class="cabinet-sa-psi-ring" style="--psi: {{ (int) ($psiSummary['avg'] ?? 0) }}">
                    <span class="cabinet-sa-psi-ring__val">{{ $psiSummary['avg'] !== null ? $psiSummary['avg'] : '—' }}</span>
                    <span class="cabinet-sa-psi-ring__lbl">Perf</span>
                </div>
                <div class="cabinet-sa-psi-hero__meta">
                    <div class="cabinet-sa-psi-hero__title">Средний Performance · {{ $strategyLabel }}</div>
                    <div class="cabinet-sa-psi-hero__sub">
                        Лабораторный Lighthouse через Google PageSpeed Insights API · {{ $psiSummary['total'] }} URL
                    </div>
                    <div class="cabinet-sa-psi-hero__chips">
                        <span class="cabinet-sa-psi-chip cabinet-sa-psi-chip--good">{{ $psiSummary['good'] }} отлично</span>
                        <span class="cabinet-sa-psi-chip cabinet-sa-psi-chip--mid">{{ $psiSummary['mid'] }} улучшить</span>
                        <span class="cabinet-sa-psi-chip cabinet-sa-psi-chip--poor">{{ $psiSummary['poor'] }} критично</span>
                    </div>
                    @if(!empty($psiSummary['worst_url']) && $psiSummary['worst_pct'] !== null)
                        <div class="cabinet-sa-psi-hero__worst">
                            Слабое звено: <strong>{{ $psiSummary['worst_pct'] }}</strong>
                            · <a href="{{ $psiSummary['worst_url'] }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($psiSummary['worst_url'], 64) }}</a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="cabinet-sa-psi-legend">
                <div class="cabinet-sa-psi-legend__row"><span class="cabinet-sa-psi-dot cabinet-sa-psi-dot--good"></span> 90–100 хорошо</div>
                <div class="cabinet-sa-psi-legend__row"><span class="cabinet-sa-psi-dot cabinet-sa-psi-dot--mid"></span> 50–89 улучшить</div>
                <div class="cabinet-sa-psi-legend__row"><span class="cabinet-sa-psi-dot cabinet-sa-psi-dot--poor"></span> 0–49 плохо</div>
                <div class="cabinet-sa-psi-legend__note">Пороги как у Google PSI / Core Web Vitals</div>
            </div>
        </div>

        <div class="cabinet-sa-psi-list">
            @foreach($psiRows as $row)
                @php
                    $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                    $pct = isset($meta['score_pct'])
                        ? (int) $meta['score_pct']
                        : (isset($meta['score']) ? (int) round(((float) $meta['score']) * 100) : null);
                    $band = SiteAuditPsiMetrics::scoreBand($pct);
                    $url = (string) ($row->url ?? '');
                    $path = $url;
                    $pu = parse_url($url);
                    if (is_array($pu)) {
                        $path = ($pu['path'] ?? '/') . (isset($pu['query']) ? '?' . $pu['query'] : '');
                        if ($path === '') {
                            $path = '/';
                        }
                    }
                    $lcp = isset($meta['lcp_ms']) ? (float) $meta['lcp_ms'] : null;
                    $cls = isset($meta['cls']) ? (float) $meta['cls'] : null;
                    $tbt = isset($meta['tbt_ms']) ? (float) $meta['tbt_ms'] : null;
                    $fcp = isset($meta['fcp_ms']) ? (float) $meta['fcp_ms'] : null;
                    $si = isset($meta['si_ms']) ? (float) $meta['si_ms'] : null;
                    $tti = isset($meta['tti_ms']) ? (float) $meta['tti_ms'] : null;
                    $ttfb = isset($meta['ttfb_ms']) ? (float) $meta['ttfb_ms'] : null;
                    $opps = is_array($meta['opportunities'] ?? null) ? $meta['opportunities'] : [];
                    $diags = is_array($meta['diagnostics'] ?? null) ? $meta['diagnostics'] : [];
                    $hasRich = !empty($meta['rich']) || $opps !== [] || isset($meta['accessibility_pct']);
                    $cats = [
                        ['k' => 'Perf', 'v' => $pct, 'band' => $band],
                        ['k' => 'A11y', 'v' => isset($meta['accessibility_pct']) ? (int) $meta['accessibility_pct'] : null],
                        ['k' => 'Best', 'v' => isset($meta['best_practices_pct']) ? (int) $meta['best_practices_pct'] : null],
                        ['k' => 'SEO', 'v' => isset($meta['seo_pct']) ? (int) $meta['seo_pct'] : null],
                    ];
                    foreach ($cats as $i => $c) {
                        if ($i === 0) {
                            continue;
                        }
                        $cats[$i]['band'] = SiteAuditPsiMetrics::scoreBand($c['v']);
                    }
                    $cwv = [
                        ['k' => 'LCP', 'tip' => 'Largest Contentful Paint — когда отрисовался главный контент', 'v' => SiteAuditPsiMetrics::formatMs($lcp), 'band' => SiteAuditPsiMetrics::lcpBand($lcp)],
                        ['k' => 'CLS', 'tip' => 'Cumulative Layout Shift — прыжки вёрстки', 'v' => SiteAuditPsiMetrics::formatCls($cls), 'band' => SiteAuditPsiMetrics::clsBand($cls)],
                        ['k' => 'TBT', 'tip' => 'Total Blocking Time — блокировка главного потока (lab)', 'v' => SiteAuditPsiMetrics::formatMs($tbt), 'band' => SiteAuditPsiMetrics::tbtBand($tbt)],
                        ['k' => 'FCP', 'tip' => 'First Contentful Paint — первая отрисовка', 'v' => SiteAuditPsiMetrics::formatMs($fcp), 'band' => SiteAuditPsiMetrics::fcpBand($fcp)],
                        ['k' => 'SI', 'tip' => 'Speed Index — скорость заполнения экрана', 'v' => SiteAuditPsiMetrics::formatMs($si), 'band' => SiteAuditPsiMetrics::siBand($si)],
                    ];
                    if ($tti !== null) {
                        $cwv[] = ['k' => 'TTI', 'tip' => 'Time to Interactive', 'v' => SiteAuditPsiMetrics::formatMs($tti), 'band' => 'unknown'];
                    }
                    if ($ttfb !== null) {
                        $cwv[] = ['k' => 'TTFB', 'tip' => 'Time to First Byte — ответ сервера', 'v' => SiteAuditPsiMetrics::formatMs($ttfb), 'band' => 'unknown'];
                    }
                    $field = is_array($meta['field'] ?? null) ? $meta['field'] : null;
                    $originField = is_array($meta['origin_field'] ?? null) ? $meta['origin_field'] : null;
                    $isIgn = !empty($ignoredMap[(int) ($row->id ?? 0)]);
                    $note = $notesMap[(int) ($row->id ?? 0)] ?? null;
                    $isFixed = is_array($note) && (($note['status'] ?? '') === 'fixed');
                    $noteComment = is_array($note) ? (string) ($note['comment'] ?? '') : '';
                    $cruxLabels = [
                        'LARGEST_CONTENTFUL_PAINT_MS' => 'LCP',
                        'CUMULATIVE_LAYOUT_SHIFT_SCORE' => 'CLS',
                        'INTERACTION_TO_NEXT_PAINT' => 'INP',
                        'FIRST_CONTENTFUL_PAINT_MS' => 'FCP',
                        'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'TTFB',
                    ];
                @endphp
                <article class="cabinet-sa-psi-card cabinet-sa-psi-band--{{ $band }}{{ $isIgn ? ' is-ignored' : '' }}{{ $isFixed ? ' is-fixed' : '' }}">
                    <div class="cabinet-sa-psi-card__top">
                        <div class="cabinet-sa-psi-card__score">
                            <div class="cabinet-sa-psi-ring cabinet-sa-psi-ring--sm" style="--psi: {{ (int) ($pct ?? 0) }}">
                                <span class="cabinet-sa-psi-ring__val">{{ $pct !== null ? $pct : '—' }}</span>
                            </div>
                            <span class="cabinet-sa-psi-card__band">{{ SiteAuditPsiMetrics::bandLabel($band) }}</span>
                        </div>
                        <div class="cabinet-sa-psi-card__url">
                            <a class="cabinet-sa-psi-card__path" href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $url }}">{{ $path }}</a>
                            <div class="cabinet-sa-psi-card__cats">
                                @foreach($cats as $c)
                                    @if($c['v'] !== null)
                                        <span class="cabinet-sa-psi-cat cabinet-sa-psi-band--{{ $c['band'] }}">{{ $c['k'] }} {{ $c['v'] }}</span>
                                    @endif
                                @endforeach
                                @if($isIgn)
                                    <span class="badge text-bg-light border">игнор</span>
                                @endif
                                @if($isFixed)
                                    <span class="badge text-bg-success">исправлено</span>
                                @endif
                            </div>
                        </div>
                        <div class="cabinet-sa-psi-card__links">
                            <a class="cabinet-sa-psi-ext" href="{{ $psiGoogleBase . urlencode($url) }}" target="_blank" rel="noopener noreferrer" title="Открыть полный отчёт Google PageSpeed">Google PSI ↗</a>
                        </div>
                    </div>

                    <div class="cabinet-sa-psi-metrics">
                        @foreach($cwv as $m)
                            <div class="cabinet-sa-psi-metric cabinet-sa-psi-band--{{ $m['band'] }}" title="{{ $m['tip'] }}">
                                <span class="cabinet-sa-psi-metric__k">{{ $m['k'] }}</span>
                                <span class="cabinet-sa-psi-metric__v">{{ $m['v'] }}</span>
                                <span class="cabinet-sa-psi-metric__b">{{ SiteAuditPsiMetrics::bandLabel($m['band']) }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($field || $originField)
                        @php $crux = $field ?: $originField; @endphp
                        <div class="cabinet-sa-psi-field">
                            <span class="cabinet-sa-psi-field__lbl">Field (CrUX{{ !$field && $originField ? ' · origin' : '' }})</span>
                            @if(!empty($crux['overall']))
                                <span class="cabinet-sa-psi-chip">{{ $crux['overall'] }}</span>
                            @endif
                            @foreach(($crux['metrics'] ?? []) as $mk => $mv)
                                <span class="cabinet-sa-psi-chip" title="{{ $mk }}">
                                    {{ $cruxLabels[$mk] ?? $mk }}
                                    @if(($mv['percentile'] ?? null) !== null)
                                        @if(($cruxLabels[$mk] ?? '') === 'CLS')
                                            {{ number_format((float) $mv['percentile'], 3) }}
                                        @else
                                            {{ SiteAuditPsiMetrics::formatMs((float) $mv['percentile']) }}
                                        @endif
                                    @endif
                                    @if(!empty($mv['category'])) · {{ $mv['category'] }}@endif
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($opps !== [] || $diags !== [])
                        <details class="cabinet-sa-psi-details" @if($band === 'poor' || $band === 'needs-improvement') open @endif>
                            <summary>
                                Что ускорить
                                @if($opps !== [])
                                    · {{ count($opps) }} возможностей
                                @endif
                                @if($diags !== [])
                                    · {{ count($diags) }} диагностик
                                @endif
                            </summary>
                            @if($opps !== [])
                                <ul class="cabinet-sa-psi-opp">
                                    @foreach($opps as $op)
                                        <li>
                                            <span class="cabinet-sa-psi-opp__title">{{ $op['title'] ?? '' }}</span>
                                            <span class="cabinet-sa-psi-opp__save">
                                                @if(!empty($op['savings_ms']))
                                                    −{{ SiteAuditPsiMetrics::formatMs((float) $op['savings_ms']) }}
                                                @endif
                                                @if(!empty($op['savings_bytes']))
                                                    · −{{ number_format(((int) $op['savings_bytes']) / 1024, 0) }} КБ
                                                @endif
                                                @if(!empty($op['display']))
                                                    · {{ $op['display'] }}
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($diags !== [])
                                <ul class="cabinet-sa-psi-diag">
                                    @foreach($diags as $d)
                                        <li>
                                            <span>{{ $d['title'] ?? '' }}</span>
                                            @if(!empty($d['display']))
                                                <span class="text-muted">{{ $d['display'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </details>
                    @elseif(!$hasRich)
                        <div class="cabinet-sa-psi-legacy">
                            {{ SiteAuditPsiMetrics::compactLine($meta) }}
                            <span class="text-muted">· полный разбор (возможности / A11y / SEO) — после следующего прогона</span>
                        </div>
                    @endif

                    @if($showActions && !empty($row->id))
                        <div class="cabinet-sa-psi-card__actions">
                            @if(!empty($canNote) && $noteComment !== '')
                                <div class="cabinet-sa-note-text">{{ $noteComment }}</div>
                            @endif
                            <div class="cabinet-sa-actions">
                                @if(!empty($canNote))
                                    <div class="cabinet-sa-act cabinet-sa-act--note">
                                        <label class="cabinet-sa-act__main" for="sa-note-{{ (int) $row->id }}">Заметка</label>
                                    </div>
                                    @if(!$isFixed)
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--fixed">
                                                <button type="submit" name="status" value="fixed" class="cabinet-sa-act__main">Исправлено</button>
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--open">
                                                <button type="submit" name="status" value="open" class="cabinet-sa-act__main">Открыть</button>
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canIgnore))
                                    @if($isIgn)
                                        <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--restore">
                                                <button type="submit" class="cabinet-sa-act__main">Вернуть</button>
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--ignore">
                                                <button type="submit" class="cabinet-sa-act__main">Игнор</button>
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canNote))
                                    <input type="checkbox" class="cabinet-sa-note-toggle" id="sa-note-{{ (int) $row->id }}">
                                    <div class="cabinet-sa-note-panel">
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-note-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <textarea name="comment" rows="2" class="form-control form-control-sm"
                                                      placeholder="Текст заметки…">{{ $noteComment }}</textarea>
                                            <button type="submit" name="status" value="{{ $isFixed ? 'fixed' : 'open' }}" class="btn btn-sm btn-primary mt-1">Сохранить</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endif
