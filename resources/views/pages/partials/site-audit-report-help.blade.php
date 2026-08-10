@php
    $help = \App\Services\SiteAudit\SiteAuditFindingHelp::forCode($code, $meta ?? []);
    $isSerpSnippetsFamily = in_array($code ?? '', [
        'serp_snippets',
        'serp_title_mismatch',
        'serp_snippet_source',
    ], true);
    $isPsiFamily = in_array($code ?? '', ['psi_mobile', 'psi_desktop'], true);
    $serpSampleMax = (int) config('site_audit.serp_snippets_max_urls', 12);
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
    $psiSampleMax = (int) config('site_audit.psi_max_urls', 20);
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
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Почему плохо</span>
        <span class="cabinet-sa-help__text">{{ $help['why'] ?? '' }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Как исправить</span>
        <span class="cabinet-sa-help__text">{{ $help['fix'] ?? '' }}</span>
    </div>
    @if($isSerpSnippetsFamily)
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Выборка</span>
            <span class="cabinet-sa-help__text">
                Не весь сайт: до <strong>{{ $serpSampleMax }}</strong> URL
                (посадочные страницы и несколько страниц из обхода), Яндекс и Google.
                @if($serpSampleDone !== null)
                    В этой проверке снято: <strong>{{ $serpSampleDone }}</strong>.
                @endif
                Полный список своих URL со сверкой TITLE с выдачей — модуль
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
                В таблицу — удачные замеры; балл ниже <strong>{{ $psiWarnPct }}%</strong> помечаем предупреждением.
                @if($psiSampleDone !== null)
                    В этой проверке замерено URL: <strong>{{ $psiSampleDone }}</strong>.
                @endif
                Обычно снимается в конце аудита; если пусто — кнопка «Запустить» ниже.
            </span>
        </div>
    @endif
    @if(!empty($showReferrers) && !empty($crawl))
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Откуда ссылки</span>
            <span class="cabinet-sa-help__text">
                @if(in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true))
                    Колонка «Откуда ссылаются» — страницы, где в HTML стоит ссылка на этот URL
                    (меню, футер, текст), либо откуда URL попал в очередь: <strong>sitemap.xml</strong>, посев, главная.
                    Чинить имеет смысл там: в ссылках сразу писать финальный адрес со слэшем/без, как на сервере.
                    Фильтр «Тип редиректа» → «Другая страница» скрывает массовые /about → /about/ и оставляет смену URL.
                @else
                    Колонка «Откуда ссылаются» — либо страницы с HTML-ссылкой на этот URL,
                    либо откуда он попал в очередь: <strong>sitemap.xml</strong>, посев (список URL), главная.
                    Обратный отчёт:
                    <a href="{{ route('pages.site-audit.report.show', [$crawl->id, 'page_has_broken_links']) }}">Страницы с битыми ссылками</a>
                    (там URL = страница-источник, в деталях — битые цели).
                @endif
            </span>
        </div>
    @endif
</div>
