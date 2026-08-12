@php
    use App\Services\SiteAudit\SiteAuditCrawlImagesColumns;
    use App\Services\SiteAudit\SiteAuditImageItem;
    $ciCatalog = SiteAuditCrawlImagesColumns::catalog();
    $ciPresets = SiteAuditCrawlImagesColumns::presets();
    $ciDefault = SiteAuditCrawlImagesColumns::defaultKeys();
    $ciMissing = SiteAuditCrawlImagesColumns::MISSING;
    $ciSortable = SiteAuditCrawlImagesColumns::sortableKeys();
    $ciSort = $crawlPagesSort ?? 'url';
    $ciDir = $crawlPagesDir ?? 'asc';
    $ciGroupLabels = ['base' => 'База', 'meta' => 'Alt / атрибуты'];
    $ciCatalogKeys = array_column($ciCatalog, 'key');
@endphp
<div class="cabinet-sa-crawl-pages-toolbar mb-2"
     data-sa-crawl-images-cols
     data-sa-cols-catalog="{{ implode(',', $ciCatalogKeys) }}">
    <div class="cabinet-sa-crawl-pages-toolbar__presets" role="group" aria-label="Пресеты столбцов">
        @foreach($ciPresets as $presetKey => $preset)
            <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-crawl-pages-preset"
                    data-sa-cols-preset="{{ $presetKey }}">{{ $preset['label'] }}</button>
        @endforeach
    </div>
    <div class="cabinet-sa-crawl-pages-toolbar__end">
        @php
            $ciPerPage = (int) ($perPage ?? \App\Services\SiteAudit\SiteAuditInventory::PER_PAGE_DEFAULT);
            $ciPerPageOpts = \App\Services\SiteAudit\SiteAuditInventory::PER_PAGE_OPTIONS;
        @endphp
        <label class="cabinet-sa-crawl-pages-perpage">
            <span class="cabinet-sa-crawl-pages-perpage__label">Строк</span>
            <select class="form-select form-select-sm cabinet-sa-crawl-pages-perpage__select"
                    data-sa-per-page
                    aria-label="Сколько строк на странице">
                @foreach($ciPerPageOpts as $opt)
                    <option value="{{ $opt }}" @if($ciPerPage === (int) $opt) selected @endif>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <details class="cabinet-sa-crawl-pages-cols">
            <summary class="btn btn-sm btn-outline-secondary">Столбцы</summary>
            <div class="cabinet-sa-crawl-pages-cols__panel" data-sa-cols-order-list>
                <div class="cabinet-sa-report-cols__hint">Перетащите строки — так задаётся порядок в таблице.</div>
                @foreach($ciCatalog as $col)
                    @php $gLabel = $ciGroupLabels[$col['group'] ?? ''] ?? ''; @endphp
                    <label class="cabinet-sa-crawl-pages-cols__item cabinet-sa-report-cols__item"
                           draggable="true"
                           data-sa-col-order="{{ $col['key'] }}">
                        <span class="cabinet-sa-report-cols__drag" aria-hidden="true">⋮⋮</span>
                        <input type="checkbox"
                               data-sa-col-toggle="{{ $col['key'] }}"
                               @if(!empty($col['locked'])) disabled @endif
                               @if(in_array($col['key'], $ciDefault, true)) checked @endif>
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
           data-sa-crawl-images-table
           data-sa-cols-default="{{ implode(',', $ciDefault) }}"
           data-sa-cols-presets='@json(collect($ciPresets)->mapWithKeys(function ($p, $k) { return [$k => $p["cols"]]; }))'>
        <thead class="table-light">
        <tr>
            @foreach($ciCatalog as $col)
                @php
                    $key = $col['key'];
                    $hidden = empty($col['default']) && empty($col['locked']);
                    $canSort = isset($ciSortable[$key]);
                    $isActive = $canSort && $ciSort === $key;
                    if ($isActive) {
                        $nextDir = $ciDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        $nextDir = SiteAuditCrawlImagesColumns::defaultDir($key);
                    }
                    $sortHref = $canSort
                        ? request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => 1])
                        : null;
                @endphp
                <th data-sa-col="{{ $key }}"
                    class="text-nowrap{{ $hidden ? ' is-col-hidden' : '' }}{{ $canSort ? ' cabinet-sa-th-sort' : '' }}{{ $isActive ? ' is-sorted is-sorted-' . $ciDir : '' }}">
                    @if($canSort)
                        <a href="{{ $sortHref }}" class="cabinet-sa-th-sort__link">
                            <span>{{ $col['label'] }}</span>
                            <span class="cabinet-sa-th-sort__mark" aria-hidden="true">
                                @if($isActive)
                                    {{ $ciDir === 'asc' ? '↑' : '↓' }}
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
                $imgUrl = (string) ($row->url ?? '');
                $pageUrl = (string) ($m['page_url'] ?? '');
                $ext = (string) ($m['ext'] ?? '');
                $https = ! empty($m['https']);
                $external = ! empty($m['external']);
                $alt = $m['alt'] ?? null;
                $hasAlt = array_key_exists('has_alt', $m) ? $m['has_alt'] : null;
                $urlLen = (int) ($m['url_len'] ?? mb_strlen($imgUrl));
                $file = (string) ($m['file'] ?? '');
                $statusInt = isset($m['status']) && $m['status'] !== null && $m['status'] !== '' ? (int) $m['status'] : null;
                $ok = array_key_exists('ok', $m) ? $m['ok'] : null;
                $sizeBytes = isset($m['size_bytes']) ? (int) $m['size_bytes'] : null;
                $sizeLabel = SiteAuditImageItem::formatSizeBytes($sizeBytes);
                $width = isset($m['width']) ? (int) $m['width'] : null;
                $height = isset($m['height']) ? (int) $m['height'] : null;
                $dimsLabel = SiteAuditImageItem::formatDimensions($width, $height);
                $loading = (string) ($m['loading'] ?? '');
                $contentType = (string) ($m['content_type'] ?? '');
            @endphp
            <tr>
                @foreach($ciCatalog as $col)
                    @php
                        $key = $col['key'];
                        $hidden = empty($col['default']) && empty($col['locked']);
                    @endphp
                    <td data-sa-col="{{ $key }}"
                        class="small{{ $hidden ? ' is-col-hidden' : '' }}{{ in_array($key, ['url_len', 'width', 'height', 'size'], true) ? ' tabular-nums text-end' : '' }}">
                        @switch($key)
                            @case('url')
                                <div class="cabinet-sa-crawl-pages__url-cell">
                                    <a class="cabinet-sa-url-break" href="{{ $imgUrl }}" target="_blank" rel="noopener noreferrer">{{ $imgUrl }}</a>
                                </div>
                                @break
                            @case('page_url')
                                @if($pageUrl !== '')
                                    <a class="cabinet-sa-url-break" href="{{ $pageUrl }}" target="_blank" rel="noopener noreferrer">{{ $pageUrl }}</a>
                                @else
                                    <span class="text-muted">{{ $ciMissing }}</span>
                                @endif
                                @break
                            @case('status')
                                @if($statusInt !== null)
                                    <span class="cabinet-sa-status-pill {{ $statusInt >= 500 ? 'cabinet-sa-status-pill--5xx' : ($statusInt >= 400 ? 'cabinet-sa-status-pill--4xx' : ($statusInt >= 300 ? 'cabinet-sa-status-pill--3xx' : 'cabinet-sa-status-pill--2xx')) }}">{{ $statusInt }}</span>
                                @elseif($ok === false)
                                    <span class="cabinet-sa-status-pill cabinet-sa-status-pill--4xx">err</span>
                                @else
                                    @include('pages.partials.site-audit-na-tip', ['tip' => "Код ответа запрашивается отдельно после обхода HTML (проба файла по URL).\nЕсли пусто — URL ещё не попал в бюджет пробы или проверка не дошла до этапа картинок.\nПерезапустите агрегацию / новую проверку, чтобы добрать."])
                                @endif
                                @break
                            @case('size')
                                @if($sizeLabel === '—')
                                    @include('pages.partials.site-audit-na-tip', ['tip' => $statusInt === null
                                        ? "Размер берётся из ответа сервера при пробе файла (Content-Length / Content-Range).\nПока нет кода ответа — размер тоже неизвестен: проба ещё не выполнялась."
                                        : "Сервер не отдал размер файла (нет Content-Length и Content-Range).\nТак бывает у CDN, chunked-ответов и части SVG."])
                                @else
                                    {{ $sizeLabel }}
                                @endif
                                @break
                            @case('dims')
                                @if($dimsLabel === '—')
                                    @include('pages.partials.site-audit-na-tip', ['tip' => "Ширина и высота берутся из HTML (атрибуты width/height у <img>).\nУ webp/jpg их часто нет в разметке — тогда прочерк; у SVG обычно указаны."])
                                @else
                                    {{ $dimsLabel }}
                                @endif
                                @break
                            @case('ext')
                                {{ $ext !== '' ? $ext : $ciMissing }}
                                @break
                            @case('https')
                                {{ $https ? 'да' : 'нет' }}
                                @break
                            @case('external')
                                {{ $external ? 'да' : 'нет' }}
                                @break
                            @case('alt')
                                @if($hasAlt === null && $alt === null)
                                    <span class="text-muted">—</span>
                                @elseif($alt === null || $alt === '')
                                    <span class="cabinet-sa-crawl-pages__missing">{{ $ciMissing }}</span>
                                @else
                                    {{ \Illuminate\Support\Str::limit($alt, 80) }}
                                @endif
                                @break
                            @case('has_alt')
                                @if($hasAlt === null)
                                    <span class="text-muted">—</span>
                                @elseif($hasAlt)
                                    да
                                @else
                                    <span class="cabinet-sa-crawl-pages__missing">нет</span>
                                @endif
                                @break
                            @case('loading')
                                {{ $loading !== '' ? $loading : '—' }}
                                @break
                            @case('content_type')
                                {{ $contentType !== '' ? $contentType : '—' }}
                                @break
                            @case('width')
                                {{ $width !== null ? number_format($width, 0, '', ' ') : '—' }}
                                @break
                            @case('height')
                                {{ $height !== null ? number_format($height, 0, '', ' ') : '—' }}
                                @break
                            @case('url_len')
                                {{ number_format($urlLen, 0, '', ' ') }}
                                @break
                            @case('file')
                                {{ $file !== '' ? $file : $ciMissing }}
                                @break
                            @default
                                —
                        @endswitch
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($ciCatalog) }}" class="text-muted px-3 py-3">Картинок в этой проверке нет</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<script src="{{ asset('js/cabinet-site-audit-crawl-images.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-crawl-images.js')) ?: time() }}" defer></script>
