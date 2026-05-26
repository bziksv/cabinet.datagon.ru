@component('component.card', ['title' => __('My project')])
    @slot('css')
        @include('backlink.partials.styles')
    @endslot

    @php
        $linksTotal = (int) ($project->total_link ?? $project->link->count());
        $linksBroken = (int) ($project->total_broken_link ?? 0);
    @endphp

    <div class="cabinet-backlink-page">
        @include('backlink.partials.toasts')
        @include('backlink.partials.module-nav', ['active' => 'show', 'project' => $project])

        @include('backlink.partials.free-tariff-email-notice')

        <div class="cabinet-bl-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-bl-lead__icon" aria-hidden="true">
                    <i class="bi bi-folder2-open"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ $project->project_name }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Backlink show lead hint') }}</p>
                </div>
            </div>
        </div>

        <div class="card cabinet-bl-project-card border shadow-sm mb-0">
            <div class="card-header py-2">
                <h4 class="card-title h6 mb-0">{{ __('Backlink project settings') }}</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-6">
                        <label class="form-label" for="cabinet-bl-project-name">{{ __('Project name') }}</label>
                        <input type="text"
                               name="project_name"
                               id="cabinet-bl-project-name"
                               class="form-control cabinet-bl-project-name"
                               value="{{ $project->project_name }}"
                               data-project-id="{{ $project->id }}">
                    </div>
                    <div class="col-md-6">
                        @include('backlink.partials.monitoring-field', [
                            'options' => $monitoring,
                            'value' => $project->monitoring_project_id,
                            'class' => ['form-select'],
                            'wrapperClass' => 'mb-0',
                            'fieldId' => 'monitoring_project_id_show',
                            'projectId' => $project->id,
                        ])
                    </div>
                </div>

                <div class="cabinet-bl-kpi-row mt-3">
                    <div class="cabinet-bl-kpi">
                        <div class="cabinet-bl-kpi__value">{{ number_format($linksTotal, 0, ',', ' ') }}</div>
                        <div class="cabinet-bl-kpi__label">{{ __('Backlink links total') }}</div>
                    </div>
                    <div class="cabinet-bl-kpi{{ $linksBroken > 0 ? ' cabinet-bl-kpi--danger' : '' }}">
                        <div class="cabinet-bl-kpi__value">{{ number_format($linksBroken, 0, ',', ' ') }}</div>
                        <div class="cabinet-bl-kpi__label">{{ __('Backlink links broken') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cabinet-bl-toolbar">
            <p class="mb-0 small text-secondary">{{ __('Backlink links in project') }}</p>
            <div class="cabinet-bl-toolbar__actions">
                <a href="{{ route('add.link.view', $project->id) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add link') }}
                </a>
                <a href="{{ route('backlink') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('To my projects') }}
                </a>
            </div>
        </div>

        @if($linksTotal === 0)
            <div class="cabinet-bl-empty">
                <i class="bi bi-link-45deg display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
                <p class="mb-0">{{ __('Backlink empty links') }}</p>
            </div>
        @else
            <div class="cabinet-bl-table-wrap">
                <table class="table table-sm cabinet-bl-table" aria-describedby="cabinet-bl-links-caption">
                    <caption id="cabinet-bl-links-caption" class="visually-hidden">{{ __('Backlink links in project') }}</caption>
                    <thead>
                    <tr>
                        <th class="cabinet-bl-col-wide">{{ __('Backlink col donor') }}</th>
                        <th class="cabinet-bl-col-wide">{{ __('Backlink col acceptor') }}</th>
                        <th>{{ __('Backlink col anchor short') }}</th>
                        <th>{{ __('Backlink col nofollow short') }}</th>
                        <th>{{ __('Backlink col noindex short') }}</th>
                        <th>{{ __('Backlink col last check') }}</th>
                        <th>{{ __('Backlink col status') }}</th>
                        <th>{{ __('Backlink col actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($project->link as $link)
                        <tr id="{{ $link->id }}">
                            <td>
                                {!! Form::textarea('site_donor', $link->site_donor, [
                                    'class' => 'form-control backlink cabinet-bl-cell-textarea',
                                    'rows' => 3,
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::textarea('link', $link->link, [
                                    'class' => 'form-control backlink cabinet-bl-cell-textarea',
                                    'rows' => 3,
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::textarea('anchor', $link->anchor, [
                                    'class' => 'form-control backlink cabinet-bl-cell-textarea',
                                    'rows' => 3,
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::select('nofollow', ['1' => __('Yes'), '0' => __('No')], $link->nofollow, [
                                    'class' => 'form-select backlink',
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::select('noindex', ['1' => __('Yes'), '0' => __('No')], $link->noindex, [
                                    'class' => 'form-select backlink',
                                ]) !!}
                            </td>
                            <td class="text-nowrap small text-secondary">
                                @isset($link->last_check)
                                    {{ $link->last_check }}
                                @else
                                    <span class="text-muted">—</span>
                                @endisset
                            </td>
                            <td class="cabinet-bl-status-cell">
                                @include('backlink.partials.status-badges', ['status' => $link->status])
                            </td>
                            <td class="cabinet-bl-actions-cell">
                                <form action="{{ route('check.link', $link->id) }}" method="get" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary click_tracking mb-1"
                                            data-click="Hands scan"
                                            type="submit"
                                            title="{{ __('Backlink scan link') }}"
                                            aria-label="{{ __('Backlink scan link') }}">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <form action="{{ route('delete.link', $link->id) }}"
                                      method="post"
                                      class="d-inline"
                                      onsubmit='return confirm(@json(__('Backlink confirm delete link')))'>
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"
                                            type="submit"
                                            title="{{ __('Backlink delete link') }}"
                                            aria-label="{{ __('Backlink delete link') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                var $page = $('.cabinet-backlink-page');
                var oldValue = '';
                var oldProjectName = '';
                var $projectEl = $page.find('.cabinet-bl-project-name');

                function showToast(type) {
                    var selector = type === 'success' ? '.success-message' : '.error-message';
                    $page.find(selector).show();
                    setTimeout(function () {
                        $page.find(selector).hide(300);
                    }, 4000);
                }

                function updateProject(url, data) {
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        url: url,
                        data: $.extend(data, {_token: $('meta[name="csrf-token"]').attr('content')}),
                        success: function () {
                            showToast('success');
                        },
                        error: function () {
                            showToast('error');
                        }
                    });
                }

                $(document).ready(function () {
                    $projectEl.on('focus', function () {
                        oldProjectName = $(this).val();
                    });

                    $projectEl.on('blur', function () {
                        if (!$(this).val().length) {
                            showToast('error');
                            return false;
                        }

                        if (oldProjectName !== $(this).val()) {
                            updateProject("{{ route('edit.backlink') }}", {
                                id: $(this).data('project-id'),
                                name: $(this).attr('name'),
                                option: $(this).val(),
                            });
                        }
                    });

                    $page.find('.backlink').on('focus', function () {
                        oldValue = $(this).val();
                    });

                    $page.find('.backlink').on('blur', function () {
                        if (oldValue !== $(this).val()) {
                            $.ajax({
                                type: 'POST',
                                dataType: 'json',
                                url: "{{ route('edit.link') }}",
                                data: {
                                    id: $(this).closest('tr').attr('id'),
                                    name: $(this).attr('name'),
                                    option: $(this).val(),
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function () {
                                    showToast('success');
                                },
                                error: function () {
                                    showToast('error');
                                }
                            });
                        }
                    });

                    $page.find('.monitoring-options').select2({
                        allowClear: true,
                        selectOnClose: true,
                        placeholder: @json(__('Backlink monitoring placeholder')),
                        sorter: function (el) {
                            return el.sort(function (a, b) {
                                a = a.text.toLowerCase();
                                b = b.text.toLowerCase();
                                return a < b ? -1 : (a > b ? 1 : 0);
                            });
                        },
                    }).on('select2:select', function (e) {
                        var wrapper = e.target.closest('.cabinet-bl-monitoring-field');
                        updateProject("{{ route('edit.backlink') }}", {
                            id: wrapper.getAttribute('data-project-id'),
                            name: e.target.getAttribute('name'),
                            option: e.params.data.id,
                        });
                    }).on('select2:clear', function (e) {
                        var wrapper = e.target.closest('.cabinet-bl-monitoring-field');
                        updateProject("{{ route('edit.backlink') }}", {
                            id: wrapper.getAttribute('data-project-id'),
                            name: e.target.getAttribute('name'),
                            option: null,
                        });
                    });
                });
            })();
        </script>
    @endslot
@endcomponent
