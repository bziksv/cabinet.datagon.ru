@component('component.card', ['title' => __('Add link')])
    @slot('css')
        @include('backlink.partials.styles')
    @endslot

    <div class="cabinet-backlink-page">
        @include('backlink.partials.module-nav', ['active' => 'add-link', 'project' => $project])

        <div class="d-flex flex-column gap-2">
            @include('backlink.partials.free-tariff-email-notice')
            @include('partials.cabinet-telegram-notify-notice', ['extraClass' => 'cabinet-bl-telegram-notice'])
        </div>

        <div class="cabinet-bl-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-bl-lead__icon" aria-hidden="true">
                    <i class="bi bi-plus-lg"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Add link') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Backlink add links lead hint') }}</p>
                </div>
            </div>
        </div>

        @include('backlink.partials.mode-nav')

        {!! Form::open(['action' => 'BacklinkController@storeLink', 'method' => 'POST', 'class' => 'cabinet-bl-express-form']) !!}
        <input type="hidden" name="id" value="{{ $id }}">
        <div class="cabinet-bl-form-panel">
            <div class="mb-3">
                <label class="form-label" for="params">{{ __('Loading links with a list') }} <span class="text-danger">*</span></label>
                <p class="form-text mb-2">{{ __('Backlink format help intro') }} <code class="user-select-all">{{ __('Backlink format example') }}</code></p>
                {!! Form::textarea('params', null, [
                    'class' => 'form-control font-monospace',
                    'id' => 'params',
                    'required' => 'required',
                    'rows' => 8,
                    'placeholder' => __('Backlink format example'),
                ]) !!}
            </div>

            @include('backlink.partials.format-help')

            <div class="cabinet-bl-form-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Add to Tracking') }}
                </button>
                <a href="{{ route('show.backlink', $id) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                <a href="{{ route('backlink') }}" class="btn btn-outline-secondary">{{ __('To my projects') }}</a>
            </div>
        </div>
        {!! Form::close() !!}

        <div class="cabinet-bl-simplified-form">
            {!! Form::open(['action' => 'BacklinkController@storeLink', 'method' => 'POST']) !!}
            <input type="hidden" name="id" value="{{ $id }}">
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
                <div class="cabinet-bl-toolbar__actions">
                    <a href="{{ route('show.backlink', $id) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                    <a href="{{ route('backlink') }}" class="btn btn-outline-secondary">{{ __('To my projects') }}</a>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>

    @slot('js')
        @include('backlink.partials.form-mode-js', ['syncMonitoring' => false])
    @endslot
@endcomponent
