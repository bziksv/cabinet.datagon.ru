@component('component.card', ['title' => __('Add link tracking')])
    @slot('css')
        @include('backlink.partials.styles')
    @endslot

    <div class="cabinet-backlink-page">
        @include('backlink.partials.module-nav', ['active' => 'create'])

        <div class="d-flex flex-column gap-2">
            @include('backlink.partials.free-tariff-email-notice')
            @include('partials.cabinet-telegram-notify-notice', ['extraClass' => 'cabinet-bl-telegram-notice'])
        </div>

        <div class="cabinet-bl-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-bl-lead__icon" aria-hidden="true">
                    <i class="bi bi-plus-circle"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Backlink create lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Backlink create lead hint') }}</p>
                </div>
            </div>
        </div>

        @include('backlink.partials.steps-nav', [
            'navLabel' => __('Backlink create steps nav'),
            'steps' => [
                ['n' => 1, 'title' => __('Backlink step 1 title'), 'anchor' => 'cabinet-bl-step-1', 'active' => true],
                ['n' => 2, 'title' => __('Backlink step 2 title'), 'anchor' => 'cabinet-bl-step-2'],
                ['n' => 3, 'title' => __('Backlink step 3 title'), 'anchor' => 'cabinet-bl-step-3'],
            ],
        ])

        {!! Form::open(['action' => 'BacklinkController@store', 'method' => 'POST', 'class' => 'cabinet-bl-express-form']) !!}
        <section class="cabinet-bl-step" id="cabinet-bl-step-1" aria-labelledby="cabinet-bl-step-1-label">
            @include('backlink.partials.step-head', [
                'n' => 1,
                'anchor' => 'cabinet-bl-step-1',
                'title' => __('Backlink step 1 title'),
                'hint' => __('Backlink step 1 hint'),
            ])
            <div class="cabinet-bl-step__body cabinet-bl-form-panel">
                <div class="mb-3">
                    <label class="form-label" for="project_name">{{ __('Project name') }} <span class="text-danger">*</span></label>
                    {!! Form::text('project_name', null, [
                        'class' => 'form-control',
                        'id' => 'project_name',
                        'required',
                        'placeholder' => __('Project name'),
                    ]) !!}
                </div>

                @include('backlink.partials.monitoring-field', [
                    'options' => $monitoring,
                    'value' => null,
                    'class' => ['form-select'],
                ])
            </div>
        </section>

        <section class="cabinet-bl-step" id="cabinet-bl-step-2" aria-labelledby="cabinet-bl-step-2-label">
            @include('backlink.partials.step-head', [
                'n' => 2,
                'anchor' => 'cabinet-bl-step-2',
                'title' => __('Backlink step 2 title'),
                'hint' => __('Backlink step 2 hint'),
            ])
            <div class="cabinet-bl-step__body">
                @include('backlink.partials.mode-nav')
            </div>
        </section>

        <section class="cabinet-bl-step" id="cabinet-bl-step-3" aria-labelledby="cabinet-bl-step-3-label">
            @include('backlink.partials.step-head', [
                'n' => 3,
                'anchor' => 'cabinet-bl-step-3',
                'title' => __('Backlink step 3 title'),
                'hint' => __('Backlink step 3 hint express'),
            ])
            <div class="cabinet-bl-step__body cabinet-bl-form-panel">
                <div class="mb-3">
                    <label class="form-label" for="params">{{ __('Loading links with a list') }} <span class="text-danger">*</span></label>
                    <p class="form-text mb-2">{{ __('Backlink format help intro') }} <code class="user-select-all">{{ __('Backlink format example') }}</code></p>
                    {!! Form::textarea('params', null, [
                        'class' => 'form-control font-monospace',
                        'id' => 'params',
                        'required',
                        'rows' => 8,
                        'placeholder' => __('Backlink format example'),
                    ]) !!}
                </div>

                @include('backlink.partials.format-help')

                <div class="cabinet-bl-form-footer">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Add to Tracking') }}
                    </button>
                    <a href="{{ route('backlink') }}" class="btn btn-outline-secondary">{{ __('To my projects') }}</a>
                </div>
            </div>
        </section>
        {!! Form::close() !!}

        <div class="cabinet-bl-simplified-form">
            {!! Form::open(['action' => 'BacklinkController@store', 'method' => 'POST']) !!}
            <section class="cabinet-bl-step" id="cabinet-bl-step-1-table" aria-labelledby="cabinet-bl-step-1-table-label">
                @include('backlink.partials.step-head', [
                    'n' => 1,
                    'anchor' => 'cabinet-bl-step-1-table',
                    'title' => __('Backlink step 1 title'),
                    'hint' => __('Backlink step 1 hint'),
                ])
                <div class="cabinet-bl-step__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="project_name_table">{{ __('Project name') }} <span class="text-danger">*</span></label>
                            {!! Form::text('project_name', null, [
                                'class' => 'form-control',
                                'id' => 'project_name_table',
                                'placeholder' => __('Project name'),
                                'required',
                            ]) !!}
                        </div>
                        <div class="col-md-6">
                            @include('backlink.partials.monitoring-field', [
                                'options' => $monitoring,
                                'value' => null,
                                'class' => ['form-select'],
                                'wrapperClass' => 'mb-0',
                                'fieldId' => 'monitoring_project_id_table',
                            ])
                        </div>
                    </div>
                </div>
            </section>

            <section class="cabinet-bl-step" id="cabinet-bl-step-2-table" aria-labelledby="cabinet-bl-step-2-table-label">
                @include('backlink.partials.step-head', [
                    'n' => 2,
                    'anchor' => 'cabinet-bl-step-2-table',
                    'title' => __('Backlink step 2 title'),
                    'hint' => __('Backlink step 2 hint'),
                ])
                <div class="cabinet-bl-step__body">
                    @include('backlink.partials.mode-nav')
                </div>
            </section>

            <section class="cabinet-bl-step" id="cabinet-bl-step-3-table" aria-labelledby="cabinet-bl-step-3-table-label">
                @include('backlink.partials.step-head', [
                    'n' => 3,
                    'anchor' => 'cabinet-bl-step-3-table',
                    'title' => __('Backlink step 3 title'),
                    'hint' => __('Backlink step 3 hint table'),
                ])
                <div class="cabinet-bl-step__body">
                    <input type="hidden" name="countRows" id="cabinet-bl-count-rows" value="1">

                    <div class="cabinet-bl-table-wrap mb-3">
                        <table id="cabinet-bl-simplified-table" class="table table-sm cabinet-bl-table">
                            <thead>
                            <tr>
                                <th class="cabinet-bl-col-wide">{{ __('Backlink col donor') }}</th>
                                <th class="cabinet-bl-col-wide">{{ __('Backlink col acceptor') }}</th>
                                <th>{{ __('Backlink col anchor short') }}</th>
                                <th>{{ __('Backlink col nofollow short') }}</th>
                                <th>{{ __('Backlink col noindex short') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr id="cabinet-bl-row-1">
                                <td>{!! Form::text('site_donor_1', null, ['class' => 'form-control', 'required']) !!}</td>
                                <td>{!! Form::text('link_1', null, ['class' => 'form-control', 'required']) !!}</td>
                                <td>{!! Form::text('anchor_1', null, ['class' => 'form-control', 'required']) !!}</td>
                                <td>{!! Form::select('nofollow_1', ['1' => __('Yes'), '0' => __('No')], null, ['class' => 'form-select']) !!}</td>
                                <td>{!! Form::select('noindex_1', ['1' => __('Yes'), '0' => __('No')], null, ['class' => 'form-select']) !!}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cabinet-bl-form-footer justify-content-between">
                        <div class="cabinet-bl-toolbar__actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Add to Tracking') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="cabinet-bl-add-row">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add row') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="cabinet-bl-remove-row" style="display: none">
                                <i class="bi bi-dash-lg me-1" aria-hidden="true"></i>{{ __('Delete row') }}
                            </button>
                        </div>
                        <a href="{{ route('backlink') }}" class="btn btn-outline-secondary">{{ __('To my projects') }}</a>
                    </div>
                </div>
            </section>
            {!! Form::close() !!}
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        @include('backlink.partials.form-mode-js', ['syncMonitoring' => true])
    @endslot
@endcomponent
