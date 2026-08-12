@php
    use App\Services\SiteAudit\SiteAuditCrawlPagesColumns;
    $cpCatalog = SiteAuditCrawlPagesColumns::catalog();
    $cpPresets = SiteAuditCrawlPagesColumns::presets();
    $cpDefault = SiteAuditCrawlPagesColumns::defaultKeys();
    $cpMissing = SiteAuditCrawlPagesColumns::MISSING;
    $cpSortable = SiteAuditCrawlPagesColumns::sortableSql();
    $cpSort = $crawlPagesSort ?? 'url';
    $cpDir = $crawlPagesDir ?? 'asc';
    $cpCatalogKeys = array_column($cpCatalog, 'key');
    $cpGroupLabels = [
        'base' => 'База',
        'meta' => 'Meta / SEO',
        'headings' => 'Заголовки',
        'content' => 'Контент',
        'links' => 'Ссылки',
    ];
    $viaLabels = [
        'sitemap' => 'sitemap',
        'link' => 'по ссылке',
        'seed' => 'посев',
        'home' => 'главная',
    ];
@endphp
<div class="cabinet-sa-crawl-pages-toolbar mb-2"
     data-sa-crawl-pages-cols
     data-sa-cols-catalog="{{ implode(',', $cpCatalogKeys) }}">
    <div class="cabinet-sa-crawl-pages-toolbar__presets" role="group" aria-label="Пресеты столбцов">
        @foreach($cpPresets as $presetKey => $preset)
            <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-crawl-pages-preset"
                    data-sa-cols-preset="{{ $presetKey }}">{{ $preset['label'] }}</button>
        @endforeach
    </div>
    <div class="cabinet-sa-crawl-pages-toolbar__end">
        @php
            $cpPerPage = (int) ($perPage ?? \App\Services\SiteAudit\SiteAuditInventory::PER_PAGE_DEFAULT);
            $cpPerPageOpts = \App\Services\SiteAudit\SiteAuditInventory::PER_PAGE_OPTIONS;
        @endphp
        <label class="cabinet-sa-crawl-pages-perpage">
            <span class="cabinet-sa-crawl-pages-perpage__label">Строк</span>
            <select class="form-select form-select-sm cabinet-sa-crawl-pages-perpage__select"
                    data-sa-per-page
                    aria-label="Сколько строк на странице">
                @foreach($cpPerPageOpts as $opt)
                    <option value="{{ $opt }}" @if($cpPerPage === (int) $opt) selected @endif>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <details class="cabinet-sa-crawl-pages-cols">
            <summary class="btn btn-sm btn-outline-secondary">Столбцы</summary>
            <div class="cabinet-sa-crawl-pages-cols__panel" data-sa-cols-order-list>
                <div class="cabinet-sa-report-cols__hint">Перетащите строки — так задаётся порядок в таблице.</div>
                @foreach($cpCatalog as $col)
                    @php $gLabel = $cpGroupLabels[$col['group'] ?? ''] ?? ''; @endphp
                    <label class="cabinet-sa-crawl-pages-cols__item cabinet-sa-report-cols__item"
                           draggable="true"
                           data-sa-col-order="{{ $col['key'] }}">
                        <span class="cabinet-sa-report-cols__drag" aria-hidden="true">⋮⋮</span>
                        <input type="checkbox"
                               data-sa-col-toggle="{{ $col['key'] }}"
                               @if(!empty($col['locked'])) disabled @endif
                               @if(in_array($col['key'], $cpDefault, true)) checked @endif>
                        <span class="cabinet-sa-report-cols__label">{{ $col['label'] }}</span>
                        @if($gLabel !== '')
                            <span class="cabinet-sa-report-cols__group-tag">{{ $gLabel }}</span>
                        @endif
                    </label>
                @endforeach
                <div class="cabinet-sa-report-cols__foot" data-sa-cols-foot>
                    <button type="button" class="btn btn-sm btn-link px-0" data-sa-cols-reset>По умолчанию</button>
                </div>
            </div>
        </details>
    </div>
</div>

<div class="cabinet-sa-table-wrap cabinet-sa-table-wrap--crawl-pages">
    <table class="table table-sm table-hover mb-0 cabinet-sa-findings-table cabinet-sa-findings-table--crawl-pages"
           data-sa-crawl-pages-table
           data-sa-cols-default="{{ implode(',', $cpDefault) }}"
           data-sa-cols-presets='@json(collect($cpPresets)->mapWithKeys(function ($p, $k) { return [$k => $p["cols"]]; }))'>
        <thead class="table-light">
        <tr>
            @foreach($cpCatalog as $col)
                @php
                    $key = $col['key'];
                    $hidden = empty($col['default']) && empty($col['locked']);
                    $canSort = isset($cpSortable[$key]) && empty($batchStats);
                    $isActive = $canSort && $cpSort === $key;
                    if ($isActive) {
                        $nextDir = $cpDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        $nextDir = SiteAuditCrawlPagesColumns::defaultDir($key);
                    }
                    $sortHref = $canSort
                        ? request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => 1])
                        : null;
                @endphp
                <th data-sa-col="{{ $key }}"
                    class="text-nowrap{{ $hidden ? ' is-col-hidden' : '' }}{{ $canSort ? ' cabinet-sa-th-sort' : '' }}{{ $isActive ? ' is-sorted is-sorted-' . $cpDir : '' }}">
                    @if($canSort)
                        <a href="{{ $sortHref }}" class="cabinet-sa-th-sort__link">
                            <span>{{ $col['label'] }}</span>
                            <span class="cabinet-sa-th-sort__mark" aria-hidden="true">
                                @if($isActive)
                                    {{ $cpDir === 'asc' ? '↑' : '↓' }}
                                @else
                                    ↕
                                @endif
                            </span>
                        </a>
                    @else
                        {{ $col['label'] }}
                    @endif
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            @php
                $m = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                $headings = is_array($m['headings'] ?? null) ? $m['headings'] : [];
                $status = $m['status_code'] ?? null;
                $statusInt = $status !== null && $status !== '' ? (int) $status : null;
                $sizeBytes = isset($m['size_bytes']) ? (int) $m['size_bytes'] : null;
                $sizeLabel = '—';
                if ($sizeBytes !== null && $sizeBytes >= 0) {
                    if ($sizeBytes < 1024) {
                        $sizeLabel = number_format($sizeBytes, 0, '', ' ') . ' байт';
                    } else {
                        $sizeLabel = str_replace('.', ',', number_format($sizeBytes / 1024, 2, '.', ' ')) . ' Кб';
                    }
                }
                $via = (string) ($m['discovered_via'] ?? '');
                $cell = function (string $key) use ($row, $m, $headings, $statusInt, $sizeLabel, $via, $viaLabels, $cpMissing) {
                    switch ($key) {
                        case 'url':
                            return null; // special
                        case 'url_len':
                            return number_format((int) ($m['url_len'] ?? mb_strlen((string) $row->url)), 0, '', ' ');
                        case 'https':
                            return !empty($m['https']) ? 'да' : 'нет';
                        case 'status':
                            return $statusInt;
                        case 'final_url':
                            return trim((string) ($m['final_url'] ?? '')) ?: '—';
                        case 'content_type':
                            return trim((string) ($m['content_type'] ?? '')) ?: '—';
                        case 'size':
                            return $sizeLabel;
                        case 'charset':
                            return trim((string) ($m['charset'] ?? '')) ?: '—';
                        case 'title':
                            return SiteAuditCrawlPagesColumns::missingOrText($m['title'] ?? null);
                        case 'title_len':
                            return number_format((int) ($m['title_len'] ?? 0), 0, '', ' ');
                        case 'description':
                            return SiteAuditCrawlPagesColumns::missingOrText($m['description'] ?? null);
                        case 'desc_len':
                            return number_format((int) ($m['desc_len'] ?? 0), 0, '', ' ');
                        case 'keywords':
                            return SiteAuditCrawlPagesColumns::missingOrText($m['keywords'] ?? null);
                        case 'keywords_len':
                            $kw = trim((string) ($m['keywords'] ?? ''));
                            return $kw === '' ? '—' : number_format((int) ($m['keywords_len'] ?? mb_strlen($kw)), 0, '', ' ');
                        case 'robots':
                            return SiteAuditCrawlPagesColumns::missingOrText($m['robots_meta'] ?? null);
                        case 'index':
                            return !empty($m['noindex']) ? 'noindex' : 'index';
                        case 'canonical':
                            return SiteAuditCrawlPagesColumns::missingOrText($m['canonical'] ?? null);
                        case 'h1':
                            return SiteAuditCrawlPagesColumns::headingCell(
                                $headings['h1'] ?? null,
                                isset($m['h1_count']) ? (int) $m['h1_count'] : (!empty($m['headings_complete']) ? count($headings['h1'] ?? []) : null)
                            );
                        case 'h2':
                            $known = array_key_exists('h2_count', $m) ? (int) $m['h2_count'] : null;
                            if ($known === null && !empty($m['headings_complete'])) {
                                $known = count($headings['h2'] ?? []);
                            }
                            return SiteAuditCrawlPagesColumns::headingCell($headings['h2'] ?? null, $known);
                        case 'h3':
                            return SiteAuditCrawlPagesColumns::headingCell(
                                $headings['h3'] ?? null,
                                array_key_exists('h3_count', $m) && $m['h3_count'] !== null
                                    ? (int) $m['h3_count']
                                    : (!empty($m['headings_complete']) ? count($headings['h3'] ?? []) : null)
                            );
                        case 'h4':
                            return SiteAuditCrawlPagesColumns::headingCell(
                                $headings['h4'] ?? null,
                                array_key_exists('h4_count', $m) && $m['h4_count'] !== null
                                    ? (int) $m['h4_count']
                                    : (!empty($m['headings_complete']) ? count($headings['h4'] ?? []) : null)
                            );
                        case 'h5':
                            return SiteAuditCrawlPagesColumns::headingCell(
                                $headings['h5'] ?? null,
                                array_key_exists('h5_count', $m) && $m['h5_count'] !== null
                                    ? (int) $m['h5_count']
                                    : (!empty($m['headings_complete']) ? count($headings['h5'] ?? []) : null)
                            );
                        case 'h6':
                            return SiteAuditCrawlPagesColumns::headingCell(
                                $headings['h6'] ?? null,
                                array_key_exists('h6_count', $m) && $m['h6_count'] !== null
                                    ? (int) $m['h6_count']
                                    : (!empty($m['headings_complete']) ? count($headings['h6'] ?? []) : null)
                            );
                        case 'h1_count':
                            return number_format((int) ($m['h1_count'] ?? 0), 0, '', ' ');
                        case 'h2_count':
                            return number_format((int) ($m['h2_count'] ?? 0), 0, '', ' ');
                        case 'h3_count':
                        case 'h4_count':
                        case 'h5_count':
                        case 'h6_count':
                            if (!array_key_exists($key, $m) || $m[$key] === null) {
                                return '—';
                            }
                            return number_format((int) $m[$key], 0, '', ' ');
                        case 'words':
                            return number_format((int) ($m['word_count'] ?? 0), 0, '', ' ');
                        case 'text_len':
                            return number_format((int) ($m['text_len'] ?? 0), 0, '', ' ');
                        case 'img':
                            return number_format((int) ($m['img_count'] ?? 0), 0, '', ' ');
                        case 'img_no_alt':
                            return number_format((int) ($m['img_without_alt'] ?? 0), 0, '', ' ');
                        case 'out_links':
                            return number_format((int) ($m['out_links'] ?? 0), 0, '', ' ');
                        case 'ext_links':
                            return number_format((int) ($m['ext_links'] ?? 0), 0, '', ' ');
                        case 'depth':
                            return isset($m['click_depth']) && $m['click_depth'] !== null && $m['click_depth'] !== ''
                                ? (string) (int) $m['click_depth']
                                : '—';
                        case 'via':
                            return $viaLabels[$via] ?? ($via !== '' ? $via : '—');
                        default:
                            return '—';
                    }
                };
            @endphp
            <tr class="{{ (($m['batch_status'] ?? '') === 'missing') ? 'cabinet-sa-crawl-pages__row--missing' : '' }}">
                @foreach($cpCatalog as $col)
                    @php
                        $key = $col['key'];
                        $hidden = empty($col['default']) && empty($col['locked']);
                        $val = $cell($key);
                        $isMissing = ($m['batch_status'] ?? '') === 'missing';
                    @endphp
                    <td data-sa-col="{{ $key }}"
                        class="small{{ $hidden ? ' is-col-hidden' : '' }}{{ in_array($key, ['url_len','title_len','desc_len','keywords_len','h1_count','h2_count','h3_count','h4_count','h5_count','h6_count','words','text_len','img','img_no_alt','out_links','ext_links','depth'], true) ? ' tabular-nums text-end' : '' }}">
                        @if($key === 'url')
                            <div class="cabinet-sa-crawl-pages__url-cell">
                                @if($isMissing)
                                    <span class="cabinet-sa-batch-badge cabinet-sa-batch-badge--miss">нет в проверке</span>
                                @elseif(($m['batch_status'] ?? '') === 'found')
                                    <span class="cabinet-sa-batch-badge cabinet-sa-batch-badge--ok">найдено</span>
                                @endif
                                @if($isMissing)
                                    <span class="cabinet-sa-url-break text-muted">{{ $row->url }}</span>
                                @else
                                    <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $row->url }}</a>
                                @endif
                                @if(!empty($m['batch_query']) && (string) $m['batch_query'] !== (string) $row->url && !$isMissing)
                                    <div class="cabinet-sa-crawl-pages__batch-q text-muted">запрос: {{ $m['batch_query'] }}</div>
                                @endif
                            </div>
                        @elseif($isMissing)
                            <span class="text-muted">—</span>
                        @elseif($key === 'status')
                            @if($statusInt !== null)
                                <span class="cabinet-sa-status-pill {{ $statusInt >= 500 ? 'cabinet-sa-status-pill--5xx' : ($statusInt >= 400 ? 'cabinet-sa-status-pill--4xx' : ($statusInt >= 300 ? 'cabinet-sa-status-pill--3xx' : 'cabinet-sa-status-pill--2xx')) }}">{{ $statusInt }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        @elseif($key === 'index')
                            @if(!empty($m['noindex']))
                                <span class="cabinet-sa-index-miss__chip cabinet-sa-index-miss__chip--warn">noindex</span>
                            @else
                                <span class="text-muted">index</span>
                            @endif
                        @elseif($key === 'canonical' && is_string($val) && $val !== $cpMissing && $val !== '—')
                            <a href="{{ $val }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ \Illuminate\Support\Str::limit($val, 48) }}</a>
                        @elseif($key === 'final_url' && is_string($val) && $val !== '—')
                            <a href="{{ $val }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ \Illuminate\Support\Str::limit($val, 48) }}</a>
                        @elseif(is_string($val) && $val === $cpMissing)
                            <span class="cabinet-sa-crawl-pages__missing">{{ $cpMissing }}</span>
                        @else
                            <span>{{ \Illuminate\Support\Str::limit((string) $val, 100) }}</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($cpCatalog) }}" class="text-secondary px-3 py-3">Страниц в этой проверке нет.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
<script>
window.__SA_CRAWL_PAGES_PRESETS = @json(collect($cpPresets)->mapWithKeys(function ($p, $k) { return [$k => $p['cols']]; }));
window.__SA_CRAWL_PAGES_DEFAULT = @json($cpDefault);
</script>
<script src="{{ asset('js/cabinet-site-audit-crawl-pages.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-crawl-pages.js')) ?: time() }}" defer></script>
