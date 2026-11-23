@php($wskVersion = config('cabinet-wordstat-keywords.version'))
@component('component.card', [
    'title' => __('Wordstat keywords'),
    'titleHtml' => e(__('Wordstat keywords')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-wordstat-keywords'])->render(),
    'documentTitle' => __('Wordstat keywords'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-wordstat-keywords.css') }}?v={{ $wskVersion }}.{{ @filemtime(public_path('css/cabinet-wordstat-keywords.css')) ?: time() }}">
    @endslot

    <div class="cabinet-wsk-page" id="cabinetWskPage" data-regions-url="{{ route('cluster.regions') }}">
        <div class="cabinet-wsk-head">
            <div class="text-muted small">
                {{ __('Запросов к Wordstat в этом месяце') }}: <strong>{{ number_format((int) $monthRequests, 0, '', ' ') }}</strong>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cabinetWskCreateModal">
                <i class="bi bi-plus-lg"></i> {{ __('Новый проект') }}
            </button>
        </div>

        @if ($projects->isEmpty())
            <div class="cabinet-wsk-empty">{{ __('Проектов пока нет. Создайте проект на сайт и соберите ключевые слова.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle cabinet-wsk-projects">
                    <thead>
                    <tr>
                        <th>{{ __('Сайт') }}</th>
                        <th class="text-end">{{ __('Групп') }}</th>
                        <th class="text-end">{{ __('Фраз') }}</th>
                        <th>{{ __('Создан') }}</th>
                        <th class="cabinet-wsk-projects__actions"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($projects as $p)
                        <tr>
                            <td>
                                <a href="{{ route('pages.wordstat-keywords.show', $p->id) }}">{{ $p->domain }}</a>
                                @if ($p->title)<span class="text-muted"> · {{ $p->title }}</span>@endif
                                @if ($p->team_id)<span class="badge text-bg-light ms-1">{{ __('команда') }}</span>@endif
                            </td>
                            <td class="text-end">{{ number_format((int) $p->groups_count, 0, '', ' ') }}</td>
                            <td class="text-end">{{ number_format((int) $p->phrases_count, 0, '', ' ') }}</td>
                            <td>{{ optional($p->created_at)->format('d.m.Y') }}</td>
                            <td class="text-end">
                                @if ((int) $p->user_id === (int) auth()->id())
                                    <form method="post" action="{{ route('pages.wordstat-keywords.destroy', $p->id) }}" class="d-inline"
                                          data-ajax-form
                                          data-cabinet-confirm="{{ __('Удалить проект :domain вместе со всеми группами и фразами?', ['domain' => $p->domain]) }}"
                                          data-cabinet-confirm-title="{{ __('Удалить проект') }}"
                                          data-cabinet-confirm-ok="Удалить"
                                          data-cabinet-confirm-danger="1">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" aria-label="{{ __('Удалить проект') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="modal fade" id="cabinetWskCreateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="{{ route('pages.wordstat-keywords.store') }}" data-ajax-form>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Новый проект') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Закрыть') }}"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="wskCreateDomain">{{ __('Сайт') }}</label>
                        <input type="text" name="domain" id="wskCreateDomain" class="form-control mb-2" placeholder="example.ru" required>
                        <label class="form-label" for="wskCreateTitle">{{ __('Название (необязательно)') }}</label>
                        <input type="text" name="title" id="wskCreateTitle" class="form-control mb-2">
                        <label class="form-label">
                            {{ __('Регионы по умолчанию') }}
                            @include('pages.partials.site-audit-tip', ['tip' => __('Регионы подставятся в настройки сбора. Их можно поменять перед каждым запуском.')])
                        </label>
                        <select name="region_ids[]" class="form-select" data-wsk-region-select multiple>
                            <option value="213" selected>Москва [213]</option>
                        </select>
                        @if (count($teams))
                            <label class="form-label mt-2" for="wskCreateTeam">
                                {{ __('Команда (необязательно)') }}
                                @include('pages.partials.site-audit-tip', ['tip' => __('Проект увидят все участники команды.')])
                            </label>
                            <select name="team_id" id="wskCreateTeam" class="form-select" data-wsk-plain-select2>
                                <option value="">{{ __('Только я') }}</option>
                                @foreach ($teams as $t)
                                    <option value="{{ $t->id }}">{{ $t->title }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Отмена') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Создать') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('partials.cabinet-confirm-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-ajax-form.js') }}?v={{ @filemtime(public_path('js/cabinet-ajax-form.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-wordstat-keywords.js') }}?v={{ $wskVersion }}.{{ @filemtime(public_path('js/cabinet-wordstat-keywords.js')) ?: time() }}"></script>
    @endslot
@endcomponent
