<section class="cabinet-sa-history" id="sa-history" data-sa-tour="history">
    <header class="cabinet-sa-history__head">
        <div class="cabinet-sa-history__titles">
            <h2 class="cabinet-sa-history__title">История проверок</h2>
            <p class="cabinet-sa-history__note mb-0" title="После окончания платного тарифа история хранится ещё 14 дней">
                @if(method_exists($crawls, 'total'))
                    {{ number_format($crawls->total(), 0, '', ' ') }}
                @else
                    {{ $crawls->count() }}
                @endif
                @if(!empty($historyDomain))
                    · «{{ $historyDomain }}»
                @endif
            </p>
        </div>
        <form method="GET" action="{{ route('pages.site-audit') }}#sa-history" class="cabinet-sa-history__search" id="sa-history-search">
            <label class="visually-hidden" for="sa-history-domain">Поиск по домену</label>
            <input type="search" class="form-control form-control-sm" id="sa-history-domain" name="domain"
                   value="{{ $historyDomain ?? '' }}"
                   placeholder="Домен…"
                   autocomplete="off">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Найти</button>
            @if(!empty($historyDomain))
                <a href="{{ route('pages.site-audit') }}#sa-history" class="btn btn-sm btn-link text-secondary px-1">×</a>
            @endif
        </form>
    </header>

    @if(!empty($historyPurgeNotice['show']))
        <div class="cabinet-sa-history__purge">
            Бесплатный тариф после платного — история удалится
            @if(($historyPurgeNotice['days_left'] ?? 0) > 0)
                через {{ $historyPurgeNotice['days_left'] }} дн. ({{ $historyPurgeNotice['purge_at'] ?? '' }}).
            @else
                скоро ({{ $historyPurgeNotice['purge_at'] ?? '' }}).
            @endif
        </div>
    @endif

    <div class="cabinet-sa-history__scroll">
        <table class="cabinet-sa-history-table" id="sa-history-table">
            <thead>
            <tr>
                <th class="cabinet-sa-ht-crawl">Проверка</th>
                <th>Команда</th>
                <th>Статус</th>
                <th>Прогресс</th>
                <th class="cabinet-sa-ht-when">Время</th>
                <th class="cabinet-sa-ht-num is-critical" title="Грубые ошибки">Гр.</th>
                <th class="cabinet-sa-ht-num is-other" title="Прочие ошибки">Пр.</th>
                <th class="cabinet-sa-ht-num is-warning" title="Предупреждения">Прд.</th>
                <th class="cabinet-sa-ht-num is-info" title="Информация">Инф.</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($crawls as $c)
                @php
                    $isCrawlOwner = auth()->id() && (int) $c->user_id === (int) auth()->id();
                    $project = $c->project;
                    $b = $c->buckets_json ?: [];
                    $stClass = $c->statusCssClass();
                    $sizeBytes = (int) (($crawlSizes ?? [])[$c->id] ?? 0);
                    $pct = $c->pages_total > 0
                        ? (int) round(100 * $c->pages_fetched / max(1, $c->pages_total))
                        : 0;
                    $finished = $c->isFinished();
                    $rawSettings = $c->settings_json_raw ?? null;
                    if (is_string($rawSettings)) {
                        $s = json_decode($rawSettings, true) ?: [];
                    } elseif (is_array($rawSettings)) {
                        $s = $rawSettings;
                    } else {
                        $s = [];
                    }
                    $concurrency = max(1, (int) ($s['concurrency'] ?? 1));
                    $speed = (string) ($s['crawl_speed'] ?? '—');
                    $rps = isset($s['rps']) ? (float) $s['rps'] : null;
                    $pagesOnly = ! empty($s['pages_only']);
                    $limitShow = (int) ($c->pages_limit ?: ($s['pages_limit'] ?? 0));
                    $domain = optional($project)->domain ?? '—';
                    $teamId = (int) (optional($project)->team_id ?? 0);
                    $teamTitle = optional(optional($project)->team)->title;
                    $fetchedN = (int) $c->pages_fetched;
                    $totalN = max(0, (int) $c->pages_total);
                    $isFailed = $c->status === 'failed' || $c->status === 'cancelled';
                    $indeterminate = ! $finished && ($totalN < 1 || in_array($c->status, ['queued', 'queued_wait', 'discovering'], true));
                    if ($finished && ! $isFailed) {
                        $fillClass = 'is-done';
                        $fillPct = 100;
                    } elseif ($isFailed) {
                        $fillClass = 'is-fail';
                        $fillPct = $totalN > 0 ? (int) round(100 * $fetchedN / max(1, $totalN)) : 100;
                        if ($fillPct < 1) { $fillPct = 100; }
                    } elseif ($indeterminate) {
                        $fillClass = 'is-wait';
                        $fillPct = 35;
                    } else {
                        $fillClass = 'is-run';
                        $fillPct = max(2, (int) $pct);
                    }
                    $labelText = ($totalN > 0 || $fetchedN > 0)
                        ? (number_format($fetchedN, 0, '', ' ') . ' / ' . number_format($totalN, 0, '', ' '))
                        : '…';
                    $startedLabel = $c->started_at
                        ? $c->started_at->format('d.m H:i')
                        : ($c->created_at ? $c->created_at->format('d.m H:i') : '—');
                    $startedFull = $c->started_at
                        ? $c->started_at->format('d.m.Y H:i')
                        : ($c->created_at ? $c->created_at->format('d.m.Y H:i') : '—');
                    $rpsLabel = $rps !== null
                        ? rtrim(rtrim(number_format($rps, 1, '.', ''), '0'), '.')
                        : null;
                    $crit = (int) ($b['critical'] ?? 0);
                    $other = (int) ($b['other'] ?? 0);
                    $warn = (int) ($b['warning'] ?? 0);
                    $info = (int) ($b['info'] ?? 0);
                    $canAssignTeam = $isCrawlOwner && !empty($teamAccessReady) && $project;
                @endphp
                <tr data-crawl-id="{{ $c->id }}"
                    data-finished="{{ $finished ? '1' : '0' }}"
                    data-status-url="{{ route('pages.site-audit.crawl.status', $c->id) }}"
                    class="{{ $finished ? '' : 'cabinet-sa-row--active' }}">
                    <td class="cabinet-sa-ht-crawl">
                        <div class="cabinet-sa-ht-crawl__row">
                            <span class="cabinet-sa-ht-crawl__id">#{{ $c->id }}</span>
                            <span class="cabinet-sa-ht-crawl__domain" data-sa-domain title="{{ $domain }}">{{ $domain }}</span>
                            @if($pagesOnly)
                                <span class="cabinet-sa-ht-tag">только URL</span>
                            @endif
                        </div>
                        <div class="cabinet-sa-ht-crawl__meta" data-sa-settings>
                            <span>{{ $concurrency }}× {{ $speed }}@if($rpsLabel) · {{ $rpsLabel }}/с@endif</span>
                            @if($limitShow > 0)
                                <span>· лимит {{ number_format($limitShow, 0, '', ' ') }}</span>
                            @endif
                            <span data-sa-size>· ~{{ \App\Services\SiteAudit\SiteAuditCrawlStorage::formatBytes($sizeBytes) }}</span>
                        </div>
                    </td>
                    <td class="cabinet-sa-ht-team" data-sa-team>
                        @if($canAssignTeam)
                            <form method="POST"
                                  action="{{ route('pages.site-audit.project.team', $project->id) }}"
                                  class="cabinet-sa-ht-team-form">
                                @csrf
                                <input type="hidden" name="return_to" value="history">
                                <select name="team_id"
                                        class="form-select form-select-sm"
                                        title="Команда сайта (для всех отчётов проекта)"
                                        onchange="this.form.submit()">
                                    <option value="0">Без команды</option>
                                    @foreach(($checklistTeams ?? []) as $team)
                                        <option value="{{ $team->id }}" @if($teamId === (int) $team->id) selected @endif>
                                            {{ $team->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @elseif($teamTitle)
                            <span class="cabinet-sa-ht-team__name" title="{{ $teamTitle }}">{{ \Illuminate\Support\Str::limit($teamTitle, 22) }}</span>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }}" data-sa-status>
                            {{ $c->statusLabelRu() }}
                        </span>
                    </td>
                    <td data-sa-progress>
                        <div class="cabinet-sa-mini-prog" title="{{ $labelText }}">
                            <span class="cabinet-sa-mini-prog__n">{{ $labelText }}</span>
                            @if(! $finished || $isFailed)
                                <span class="cabinet-sa-mini-prog__bar"><i class="{{ $fillClass }}" style="width:{{ $fillPct }}%"></i></span>
                            @endif
                        </div>
                    </td>
                    <td class="cabinet-sa-ht-when">
                        <div data-sa-started title="Начало: {{ $startedFull }}">{{ $startedLabel }}</div>
                        <div class="cabinet-sa-ht-when__end" data-sa-finished>
                            @if($c->finished_at)
                                {{ $c->finished_at->format('d.m H:i') }}
                            @elseif($finished)
                                —
                            @else
                                @php $eta = $c->estimateFinishedAtFormatted(); @endphp
                                @if($eta)
                                    <span class="text-muted" title="Оценка конца">~{{ $eta }}</span>
                                @else
                                    <span class="text-muted">идёт…</span>
                                @endif
                            @endif
                        </div>
                    </td>
                    <td class="cabinet-sa-ht-num is-critical" data-sa-bucket="critical">{{ number_format($crit, 0, '', ' ') }}</td>
                    <td class="cabinet-sa-ht-num is-other" data-sa-bucket="other">{{ number_format($other, 0, '', ' ') }}</td>
                    <td class="cabinet-sa-ht-num is-warning" data-sa-bucket="warning">{{ number_format($warn, 0, '', ' ') }}</td>
                    <td class="cabinet-sa-ht-num is-info" data-sa-bucket="info">{{ number_format($info, 0, '', ' ') }}</td>
                    <td class="cabinet-sa-ht-actions">
                        <span class="cabinet-sa-row-actions">
                            <a class="btn btn-sm btn-primary" href="{{ route('pages.site-audit.crawl.show', $c->id) }}">Сводка</a>
                            @if($isCrawlOwner && ! $finished)
                                <form method="POST" action="{{ route('pages.site-audit.crawl.cancel', $c->id) }}" class="d-inline"
                                      data-sa-cancel-crawl
                                      data-cabinet-confirm="Остановить краул #{{ $c->id }}?"
                                      data-cabinet-confirm-title="Остановка краула"
                                      data-cabinet-confirm-ok="Остановить"
                                      data-cabinet-confirm-danger="1">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Стоп</button>
                                </form>
                            @endif
                            @if($isCrawlOwner && $finished)
                                @php
                                    $canResume = (new \App\Services\SiteAudit\SiteAuditCrawlEngine())->canResume($c);
                                @endphp
                                @if($canResume)
                                    <form method="POST" action="{{ route('pages.site-audit.crawl.continue', $c->id) }}" class="d-inline"
                                          data-cabinet-confirm="Продолжить краул #{{ $c->id }}?"
                                          data-cabinet-confirm-title="Продолжить краул"
                                          data-cabinet-confirm-ok="Продолжить">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Далее</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('pages.site-audit.crawl.repeat', $c->id) }}" class="d-inline"
                                      data-cabinet-confirm="Повторить краул для {{ e($domain) }}?"
                                      data-cabinet-confirm-title="Новый краул"
                                      data-cabinet-confirm-ok="Повторить">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Повторить">↻</button>
                                </form>
                                <form method="POST" action="{{ route('pages.site-audit.crawl.destroy', $c->id) }}" class="d-inline"
                                      data-cabinet-confirm="Удалить краул #{{ $c->id }}?"
                                      data-cabinet-confirm-title="Удаление краула"
                                      data-cabinet-confirm-ok="Удалить"
                                      data-cabinet-confirm-danger="1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </span>
                    </td>
                </tr>
            @empty
                <tr data-sa-empty>
                    <td colspan="10" class="cabinet-sa-history__empty">История пуста — запустите первую проверку.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($crawls, 'hasPages') && $crawls->hasPages())
        <div class="cabinet-sa-history__pager">
            <div class="small text-secondary">
                {{ $crawls->firstItem() }}–{{ $crawls->lastItem() }}
                из {{ number_format($crawls->total(), 0, '', ' ') }}
            </div>
            <nav>{{ $crawls->links('pagination::bootstrap-4') }}</nav>
        </div>
    @endif
</section>
