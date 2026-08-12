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
            <span class="cabinet-sa-help__label">Сколько страниц</span>
            <span class="cabinet-sa-help__text">
                @if($serpSampleDone !== null)
                    В этой проверке посмотрели <strong>{{ number_format($serpSampleDone, 0, '', ' ') }}</strong>
                    адресов в Яндексе и Google
                    (один съём на URL → сниппеты, TITLE и источник).
                @else
                    Обычно берём до <strong>{{ number_format($serpSampleMax, 0, '', ' ') }}</strong> адресов
                    (из мониторинга позиций и из обхода), не весь сайт.
                    На каждый адрес — один запрос к выдаче, все отчёты читают один пакет.
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
    @if(($code ?? '') === 'landing_plagiarism_suspect' && !empty($crawl) && empty($isPublic))
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Проверить</span>
            <span class="cabinet-sa-help__text">
                Это внутренние дубли на своём сайте (считается при обходе).
                Внешняя проверка vs интернет — отдельно, на вкладке Антиплагиат.
                <div class="mt-2">
                    <a class="btn btn-sm btn-outline-primary"
                       href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}#sa-pane-plagiarism">
                        Открыть Антиплагиат
                    </a>
                </div>
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
