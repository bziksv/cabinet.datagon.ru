{{-- Баннеры deep-link на смежные модули. Ожидает $linkGroup = tech|seo --}}
@php
    $linkGroup = $linkGroup ?? 'seo';
    $moduleLinks = [
        'seo' => [
            [
                'title' => 'Конкуренты сайта',
                'text' => 'Сравнение с ТОП выдачи — в модуле «Анализ конкурентов», без повторного краула.',
                'route' => 'competitor.analysis',
                'btn' => 'Открыть анализ конкурентов',
            ],
            [
                'title' => 'Проверка индексации',
                'text' => 'Сниппеты и статус в индексе по URL. В аудите уже есть lite-отчёты по индексу/SERP.',
                'route' => 'pages.index-check',
                'btn' => 'Открыть проверку индексации',
            ],
            [
                'title' => 'Мониторинг мета-тегов',
                'text' => 'История и сравнение META. В аудите — snapshot seo_meta_errors по краулу.',
                'route' => 'meta-tags.index',
                'btn' => 'Открыть мета-теги',
            ],
            [
                'title' => 'Есенин / проверка текста',
                'text' => 'Полный текстовый разбор. В аудите — lite тошнота, переспам и n-граммы.',
                'route' => 'pages.esenin-text-check',
                'btn' => 'Открыть Есенин',
            ],
        ],
        'tech' => [
            [
                'title' => 'HTTP-заголовки',
                'text' => 'Разовая проверка заголовков URL. В аудите — security pack по всему краулу.',
                'route' => 'pages.headers',
                'btn' => 'Открыть HTTP-заголовки',
            ],
        ],
    ];
    $links = $moduleLinks[$linkGroup] ?? [];
@endphp
@foreach($links as $link)
    @php
        try {
            $href = route($link['route']);
        } catch (\Throwable $e) {
            $href = null;
        }
    @endphp
    @if($href)
        <div class="alert alert-light border cabinet-sa-module-link mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
                <div>
                    <strong>{{ $link['title'] }}</strong>
                    <div class="small text-muted mb-0">{{ $link['text'] }}</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ $href }}" target="_blank" rel="noopener">
                    {{ $link['btn'] }} <i class="fa fa-external-link" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @endif
@endforeach
