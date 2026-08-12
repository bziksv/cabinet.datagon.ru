@extends('layouts.public-module')

@section('title', ($meta['title'] ?? $code) . ' · аудит')

@php
    $saNeedsSelect2 = in_array(($code ?? ''), ['crawl_pages', 'crawl_images', 'security_headers'], true);
@endphp

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @if($saNeedsSelect2)
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    @endif
@endsection

@section('content')
    <div class="mb-3">
        <a href="{{ route('site-audit.public.share.view', $token) }}" class="btn btn-sm btn-outline-secondary">← К сводке</a>
        <a href="{{ route('site-audit.public.share.csv', [$token, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-primary">CSV</a>
        <a href="{{ route('site-audit.public.share.report.xlsx', [$token, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-success">XLSX</a>
        <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-print-btn" onclick="window.print()">Печать</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2">
            <h1 class="card-title h5 mb-0">
                {{ $meta['title'] ?? $code }}
                @if(!empty($meta['severity']))
                    {!! \App\Services\SiteAudit\SiteAuditFindingPresenter::severityBadgeHtml((string) $meta['severity']) !!}
                @endif
                · проверка #{{ $crawl->id }}
            </h1>
        </div>
        <div class="card-body cabinet-sa-page p-3">
            <div class="mb-2 text-muted small">
                @if(!empty($meta['inventory']))
                    В таблице: <strong>{{ number_format((int) $total, 0, '', ' ') }}</strong>
                    @if(!empty($filtersActive))
                        <span class="text-primary">(с фильтром)</span>
                    @endif
                @else
                    {{ optional($project)->domain }} ·
                    @if(!empty($meta['severity']))
                        {!! \App\Services\SiteAudit\SiteAuditFindingPresenter::severityBadgeHtml((string) $meta['severity']) !!}
                        ·
                    @endif
                    находок: <strong>{{ number_format((int) $total, 0, '', ' ') }}</strong>
                    @if(!empty($filtersActive))
                        <span class="text-primary">(с фильтром)</span>
                    @endif
                @endif
            </div>

            <div class="cabinet-sa-desc mb-3">
                @include('pages.partials.site-audit-report-help')
            </div>

            @php
                $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
                $isCrawlPagesReport = ($code ?? '') === 'crawl_pages';
                $isCrawlImagesReport = ($code ?? '') === 'crawl_images';
            @endphp
            @include('pages.partials.site-audit-report-filters')

            @if(!empty($groupable) && ($code ?? '') === 'crawl_images')
                <div class="cabinet-sa-view-toggle mb-3">
                    <span class="cabinet-sa-view-toggle__label">Вид:</span>
                    <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">По картинкам</a>
                    <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">По вхождениям</a>
                    @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                        <span class="text-muted small ms-2">картинок: {{ number_format((int) $groupTotal, 0, '', ' ') }} · вхождений: {{ number_format((int) $total, 0, '', ' ') }}</span>
                    @endif
                </div>
            @endif

            @if(!empty($htmlSitewide) && is_array($htmlSitewide) && ($code ?? '') === 'crawl_images')
                <div class="alert alert-warning border small mb-3 cabinet-sa-html-sitewide">
                    <strong>Скорее общий блок</strong> —
                    одна и та же картинка на
                    <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
                    из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
                    ({{ (int) $htmlSitewide['pct'] }}%).
                </div>
            @endif

            @if($isCrawlPagesReport)
                @include('pages.partials.site-audit-crawl-pages-table')
            @elseif($isCrawlImagesReport && ($viewMode ?? 'groups') === 'groups')
                <div class="cabinet-sa-dup-groups">
                    @forelse(($groups ?? []) as $gi => $group)
                        @php $tone = $gi % 6; @endphp
                        <div class="cabinet-sa-dup-group cabinet-sa-dup-group--t{{ $tone }}{{ !empty($group['likely_template']) ? ' cabinet-sa-dup-group--template' : '' }}">
                            <div class="cabinet-sa-dup-group__head">
                                <div class="cabinet-sa-dup-group__meta">
                                    <span class="cabinet-sa-dup-group__count">{{ number_format((int) $group['size'], 0, '', ' ') }} стр.</span>
                                    @if(!empty($group['likely_template']))
                                        <span class="cabinet-sa-dup-group__badge">общий блок</span>
                                    @endif
                                </div>
                                <div class="cabinet-sa-dup-group__label">
                                    @if(!empty($group['host']))
                                        <span class="cabinet-sa-dup-group__host">{{ $group['host'] }}</span>
                                    @endif
                                    <a href="{{ $group['href'] }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $group['href'] }}</a>
                                </div>
                            </div>
                            @if(!empty($group['hint']))
                                <div class="cabinet-sa-dup-group__hint">{{ $group['hint'] }}</div>
                            @endif
                            @php
                                $groupUrls = is_array($group['urls'] ?? null) ? $group['urls'] : [];
                                $groupUrlsHead = array_slice($groupUrls, 0, 10);
                            @endphp
                            <ul class="cabinet-sa-dup-group__urls">
                                @foreach($groupUrlsHead as $u)
                                    <li><a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a></li>
                                @endforeach
                            </ul>
                            @if(count($groupUrls) > 10)
                                <div class="text-muted small">…и ещё {{ number_format(count($groupUrls) - 10, 0, '', ' ') }} стр.</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted px-3 py-3">Картинок нет</div>
                    @endforelse
                </div>
            @elseif($isCrawlImagesReport)
                @include('pages.partials.site-audit-crawl-images-table')
            @else
            <div class="cabinet-sa-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:50%">URL</th>
                        <th>Приоритет</th>
                        <th>Детали</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="cabinet-sa-url">
                                <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer">{{ $row->url }}</a>
                            </td>
                            <td>{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($row->severity) }}</td>
                            <td class="small">
                                @php
                                    $detailsHtml = \App\Services\SiteAudit\SiteAuditFindingPresenter::metaDetailsHtml(
                                        $row->code ?? $code,
                                        $row->meta_json,
                                        $row->url
                                    );
                                @endphp
                                @if($detailsHtml !== null)
                                    {!! $detailsHtml !!}
                                @else
                                    {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::metaLine($row->code ?? $code, $row->meta_json, $row->url) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted px-3 py-3">Находок нет</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @endif

            @include('pages.partials.site-audit-pager', [
                'page' => $page,
                'pages' => $pages,
                'total' => (($viewMode ?? '') === 'groups' && !empty($groupTotal)) ? $groupTotal : ($total ?? null),
            ])
        </div>
    </div>
    @if(!empty($saNeedsSelect2))
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        @if(in_array(($code ?? ''), ['crawl_pages', 'crawl_images'], true))
            <script src="{{ asset('js/cabinet-site-audit-crawl-filters.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-crawl-filters.js')) ?: time() }}"></script>
        @endif
        <script>
            (function () {
                function initSaMulti() {
                    if (!window.jQuery || !jQuery.fn.select2) return;
                    var $gearPanel = jQuery('.cabinet-sa-filters-gear__panel').first();
                    jQuery('[data-sa-select2-multi]').each(function () {
                        var $el = jQuery(this);
                        if ($el.hasClass('select2-hidden-accessible')) {
                            $el.select2('destroy');
                        }
                        var opts = {
                            theme: 'bootstrap4',
                            width: '100%',
                            placeholder: $el.attr('data-placeholder') || 'Выберите…',
                            allowClear: true,
                            closeOnSelect: false,
                            language: {
                                noResults: function () { return 'Ничего не найдено'; },
                                searching: function () { return 'Поиск…'; }
                            }
                        };
                        if ($gearPanel.length && $el.closest('.cabinet-sa-filters-gear__panel').length) {
                            opts.dropdownParent = $gearPanel;
                        }
                        $el.select2(opts);
                    });
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initSaMulti);
                } else {
                    initSaMulti();
                }
            })();
        </script>
    @endif
@endsection
