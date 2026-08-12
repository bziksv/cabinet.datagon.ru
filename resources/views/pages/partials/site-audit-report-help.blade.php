@php
    $help = \App\Services\SiteAudit\SiteAuditFindingHelp::forCode($code, $meta ?? []);
    $isSerpSnippetsFamily = in_array($code ?? '', [
        'serp_snippets',
        'serp_title_mismatch',
        'serp_snippet_source',
    ], true);
    $isPsiFamily = in_array($code ?? '', ['psi_mobile', 'psi_desktop'], true);
    $helpSeverity = (string) (($meta['severity'] ?? '') ?: '');
    $whyLabel = in_array($helpSeverity, ['info'], true) || ($code ?? '') === 'serp_snippets'
        ? 'Зачем смотреть'
        : 'Почему плохо';
    $fixLabel = ($code ?? '') === 'serp_snippets'
        ? 'Что делать'
        : 'Как исправить';
    $serpSampleMax = (int) config('site_audit.serp_snippets_max_urls', 30);
    $serpSampleDone = null;
    if ($isSerpSnippetsFamily && !empty($crawl)) {
        $serpProgress = is_array($crawl->progress_json['serp_snippets'] ?? null)
            ? $crawl->progress_json['serp_snippets']
            : [];
        if (isset($serpProgress['sampled'])) {
            $serpSampleDone = (int) $serpProgress['sampled'];
        } else {
            $serpSampleDone = (int) \App\SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', 'serp_snippets')
                ->count();
            if ($serpSampleDone < 1) {
                $serpSampleDone = null;
            }
        }
    }
    $psiSampleMax = (int) config('site_audit.psi_max_urls', config('site_audit.serp_snippets_max_urls', 30));
    $psiWarnPct = (int) round(((float) config('site_audit.psi_score_warn', 0.5)) * 100);
    $psiSampleDone = null;
    if ($isPsiFamily && !empty($crawl)) {
        $psiProgress = is_array($crawl->progress_json['psi'] ?? null)
            ? $crawl->progress_json['psi']
            : [];
        if (isset($psiProgress['sampled'])) {
            $psiSampleDone = (int) $psiProgress['sampled'];
        }
    }
@endphp
<div class="cabinet-sa-help mb-3">
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Что это</span>
        <span class="cabinet-sa-help__text">{{ $help['what'] ?? '' }}</span>
    </div>
    @php
        $isRedirectFamily = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
    @endphp
    @if(!empty($showReferrers) && !empty($crawl) && $isRedirectFamily)
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Откуда</span>
            <span class="cabinet-sa-help__text">
                Колонка «Откуда» — где нашли этот URL:
                страница с HTML-ссылкой (меню, футер, текст), <strong>sitemap.xml</strong>, посев или главная.
                Править нужно там: сразу финальный адрес со слэшем/без, как отвечает сервер.
            </span>
        </div>
    @endif
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">{{ $whyLabel }}</span>
        <span class="cabinet-sa-help__text">{{ $help['why'] ?? '' }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">{{ $fixLabel }}</span>
        <span class="cabinet-sa-help__text">{{ $help['fix'] ?? '' }}</span>
    </div>
    @if($isSerpSnippetsFamily)
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Выборка</span>
            <span class="cabinet-sa-help__text">
                @if($serpSampleDone !== null)
                    В поиске Яндекса и Google сняли
                    <strong>{{ number_format($serpSampleDone, 0, '', ' ') }}</strong>
                    адресов (один съём на URL → сниппеты, TITLE и источник).
                @else
                    Обычно берём до <strong>{{ number_format($serpSampleMax, 0, '', ' ') }}</strong> адресов
                    (из мониторинга позиций и из обхода), не весь сайт.
                    На каждый адрес — один запрос к выдаче, все отчёты читают один пакет.
                @endif
                @if(($code ?? '') === 'serp_title_mismatch')
                    В таблице — все снятые адреса: расхождения сверху, совпадения помечены «всё ок»
                    @if(isset($total))
                        (сейчас
                        <strong>{{ number_format((int) $total, 0, '', ' ') }}</strong>
                        @if($serpSampleDone !== null && (int) $serpSampleDone > 0)
                            из {{ number_format($serpSampleDone, 0, '', ' ') }}
                        @endif
                        )
                    @endif.
                    В меню слева — только число проблем
                    @php
                        $serpMismatchN = null;
                        if (!empty($crawl)) {
                            $serpMismatchN = (int) (\App\Services\SiteAudit\SiteAuditSerpSnippetsProbe::countMismatchFindings((int) $crawl->id));
                        }
                    @endphp
                    @if($serpMismatchN !== null)
                        (<strong>{{ number_format($serpMismatchN, 0, '', ' ') }}</strong>).
                    @else
                        .
                    @endif
                @elseif(($code ?? '') === 'serp_snippets')
                    Ниже — все снятые адреса (это не список ошибок).
                @elseif(($code ?? '') === 'serp_snippet_source')
                    Ниже — те же снятые адреса с оценкой, откуда похоже взяли текст.
                @endif
                Нужен свой длинный список URL —
                <a href="{{ route('pages.index-check') }}" target="_blank" rel="noopener">Проверка индексации</a>.
            </span>
        </div>
    @endif
    @if($isPsiFamily)
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Выборка</span>
            <span class="cabinet-sa-help__text">
                Не весь сайт — до <strong>{{ $psiSampleMax }}</strong> адресов:
                посадочные из мониторинга, главная, потом ближайшие страницы из обхода.
                Каждый адрес меряется и для телефона, и для компьютера
                (отчёты «Мобильные» и «Компьютеры»).
                В отчёте явно указано устройство: <strong>телефон</strong> или <strong>компьютер</strong>
                (два соседних пункта в дереве). Карточки — балл, метрики по-русски, цвет по порогам Google и блок «Что ускорить».
                Балл ниже <strong>{{ $psiWarnPct }}%</strong> помечаем предупреждением.
                @if($psiSampleDone !== null)
                    В этой проверке замерено URL: <strong>{{ $psiSampleDone }}</strong>.
                @endif
                Обычно снимается в конце аудита; если пусто — кнопка «Запустить» ниже.
            </span>
        </div>
    @endif
    @if(($code ?? '') === 'landing_plagiarism_external' && !empty($crawl))
        @php
            $plagHelp = is_array($crawl->progress_json['plagiarism_external'] ?? null)
                ? $crawl->progress_json['plagiarism_external']
                : [];
            $plagHelpRows = is_array($plagHelp['rows'] ?? null) ? $plagHelp['rows'] : [];
            $plagHelpWarn = (float) config('site_audit.plagiarism_external_warn_below', 70);
            $plagHelpDone = (int) ($plagHelp['done'] ?? count($plagHelpRows));
            $plagHelpSt = (string) ($plagHelp['status'] ?? '');
        @endphp
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Проверили</span>
            <span class="cabinet-sa-help__text">
                @if(in_array($plagHelpSt, ['queued', 'running'], true))
                    Идёт проверка
                    @if($plagHelpDone > 0 || (int) ($plagHelp['total'] ?? 0) > 0)
                        ({{ number_format($plagHelpDone, 0, '', ' ') }}
                        из {{ number_format((int) ($plagHelp['total'] ?? $plagHelpDone), 0, '', ' ') }})
                    @endif
                    — ниже появятся URL с процентами.
                @elseif($plagHelpRows !== [])
                    В этой проверке сверили
                    <strong>{{ number_format(count($plagHelpRows), 0, '', ' ') }}</strong>
                    {{ count($plagHelpRows) === 1 ? 'страницу' : (count($plagHelpRows) < 5 ? 'страницы' : 'страниц') }}
                    (порог замечания — ниже {{ rtrim(rtrim(number_format($plagHelpWarn, 1, ',', ' '), '0'), ',') }}%).
                    Таблица замечаний — только те, кто ниже порога; весь список с процентами — в блоке ниже.
                @else
                    После обхода обычно сами берём главную, категорию и товар/услугу.
                    Если списка ещё нет — откройте «Антиплагиат» и доберите страницы вручную.
                @endif
                @if(empty($isPublic))
                    <div class="mt-2">
                        <a class="btn btn-sm btn-outline-primary"
                           href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}#sa-pane-plagiarism">
                            Открыть Антиплагиат
                        </a>
                    </div>
                @endif
            </span>
        </div>
    @endif
    @if(!empty($showReferrers) && !empty($crawl) && empty($isRedirectFamily))
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Откуда ссылки</span>
            <span class="cabinet-sa-help__text">
                Колонка «Откуда ссылаются» — либо страницы с HTML-ссылкой на этот URL,
                либо откуда он попал в очередь: <strong>sitemap.xml</strong>, посев (список URL), главная.
                Обратный отчёт:
                <a href="{{ route('pages.site-audit.report.show', [$crawl->id, 'page_has_broken_links']) }}">Страницы с битыми ссылками</a>
                (там URL = страница-источник, в деталях — битые цели).
            </span>
        </div>
    @endif
</div>
