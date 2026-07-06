@php use Illuminate\Support\Facades\Auth; @endphp
@component('component.card', [
    'title' => __('Add a monitored domain'),
    'titleHtml' => e(__('Add a monitored domain')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-monitoring'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-monitoring.css') }}?v={{ @filemtime(public_path('css/cabinet-site-monitoring.css')) ?: time() }}">
    @endslot

    <div class="cabinet-site-mon-page cabinet-site-mon-create">
        @include('site-monitoring.partials.module-nav', ['active' => 'projects', 'admin' => $admin ?? false])

        <div class="d-flex flex-column gap-2 mb-3">
            @include('site-monitoring.partials.free-tariff-email-notice')
            @include('partials.cabinet-telegram-notify-notice', ['extraClass' => 'cabinet-sm-cabinet-only-notice'])
        </div>

        <p class="text-secondary small mb-2">{{ __('Site monitoring create lead short') }}</p>
        @include('site-monitoring.partials.create-steps-nav')

        {!! Form::open(['action' => 'MonitoringDomainController@store', 'method' => 'POST', 'id' => 'cabinet-sm-create-form']) !!}

        <div class="cabinet-sm-form-sections d-flex flex-column gap-3">
            <section class="cabinet-sm-form-section" id="cabinet-sm-step-1">
                <h3 class="cabinet-sm-form-section__title">
                    <span class="cabinet-sm-step-badge" aria-hidden="true">1</span>
                    <span class="cabinet-sm-form-section__title-text">
                        <span class="cabinet-sm-form-section__step">{{ __('Site monitoring step label', ['n' => 1]) }}</span>
                        {{ __('Site monitoring form section main') }}
                    </span>
                </h3>
                <div class="cabinet-sm-form-section__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="project_name">{{ __('Project name') }}</label>
                            {!! Form::text('project_name', old('project_name'), [
                                'class' => 'form-control',
                                'id' => 'project_name',
                                'required' => true,
                                'placeholder' => __('Site monitoring placeholder project name'),
                            ]) !!}
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="link">{{ __('Link') }}</label>
                            {!! Form::text('link', old('link'), [
                                'class' => 'form-control',
                                'id' => 'link',
                                'required' => true,
                                'placeholder' => 'https://example.ru/',
                                'inputmode' => 'url',
                                'autocomplete' => 'url',
                            ]) !!}
                            <div class="form-text">{{ __('Site monitoring hint link short') }}</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="timing">{{ __('Frequency of checks') }}</label>
                            {!! Form::select('timing', $timingOptions ?? [], old('timing', $defaultTiming ?? 10), [
                                'class' => 'form-select',
                                'id' => 'timing',
                                'required' => true,
                            ]) !!}
                            @if($onFreeTariff ?? false)
                                <div class="form-text">{{ __('Site monitoring free tariff timing hint') }}</div>
                            @endif
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="waiting_time">{{ __('Response waiting time') }}</label>
                            {!! Form::select('waiting_time', [
                                '10' => '10 ' . __('sec'),
                                '15' => '15 ' . __('sec'),
                                '20' => '20 ' . __('sec'),
                            ], old('waiting_time', 10), ['class' => 'form-select', 'id' => 'waiting_time']) !!}
                            <div class="form-text">{{ __('Site monitoring hint waiting time short') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cabinet-sm-form-section" id="cabinet-sm-step-2">
                <h3 class="cabinet-sm-form-section__title">
                    <span class="cabinet-sm-step-badge" aria-hidden="true">2</span>
                    <span class="cabinet-sm-form-section__title-text">
                        <span class="cabinet-sm-form-section__step">{{ __('Site monitoring step label', ['n' => 2]) }}</span>
                        {{ __('Site monitoring form section page check') }}
                    </span>
                </h3>
                <div class="cabinet-sm-form-section__body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox"
                               class="form-check-input checkbox"
                               id="cabinet-sm-phrase-search"
                               checked
                               aria-controls="cabinet-sm-phrase-fields">
                        <label class="form-check-label" for="cabinet-sm-phrase-search">
                            {{ __('Site monitoring phrase check enabled') }}
                        </label>
                    </div>

                    <div id="cabinet-sm-phrase-fields">
                        <div class="keyword-phrase">
                            <label class="form-label" for="phrase">{{ __('Keyword') }}</label>
                            {!! Form::text('phrase', old('phrase'), [
                                'class' => 'form-control',
                                'id' => 'phrase',
                                'required' => true,
                                'placeholder' => __('Site monitoring placeholder phrase'),
                            ]) !!}
                            <div class="form-text">{{ __('Site monitoring hint phrase short') }}</div>
                        </div>
                    </div>

                    <div id="notification"
                         class="alert alert-light border small py-2 px-3 mb-0 mt-3 d-none"
                         role="note">
                        {{ __('Site monitoring http200 mode short') }}
                    </div>
                </div>
            </section>

            <section class="cabinet-sm-form-section" id="cabinet-sm-step-3">
                <h3 class="cabinet-sm-form-section__title">
                    <span class="cabinet-sm-step-badge" aria-hidden="true">3</span>
                    <span class="cabinet-sm-form-section__title-text">
                        <span class="cabinet-sm-form-section__step">{{ __('Site monitoring step label', ['n' => 3]) }}</span>
                        {{ __('Site monitoring form section notify') }}
                    </span>
                </h3>
                <div class="cabinet-sm-form-section__body">
                    <input type="hidden" name="notify_telegram" value="0">
                    <input type="hidden" name="notify_email" value="0">
                    <div class="cabinet-sm-notify-group">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox"
                                   class="form-check-input"
                                   name="notify_telegram"
                                   value="1"
                                   id="cabinet-sm-notify-telegram"
                                   @if(old('notify_telegram', ($onFreeTariff ?? false) ? false : ($defaultNotify ?? true))) checked @endif>
                            <label class="form-check-label" for="cabinet-sm-notify-telegram">{{ __('Telegram') }}</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox"
                                   class="form-check-input"
                                   name="notify_email"
                                   value="1"
                                   id="cabinet-sm-notify-email"
                                   @if(old('notify_email')) checked @endif
                                   @if(!($siteMonitoringEmailAvailable ?? true)) disabled @endif>
                            <label class="form-check-label @if(!($siteMonitoringEmailAvailable ?? true)) text-secondary @endif"
                                   for="cabinet-sm-notify-email">{{ __('Email') }}</label>
                        </div>
                    </div>
                    <div class="form-text mt-2 mb-0">{{ __('Site monitoring hint notifications short') }}</div>
                    @if($onFreeTariff ?? false)
                        <div class="form-text">{{ __('Site monitoring free tariff notify hint') }}</div>
                    @endif
                    @include('site-monitoring.partials.notify-checkbox-hint')
                </div>
            </section>
        </div>

        <div class="cabinet-sm-create-submit d-flex flex-wrap align-items-center gap-2 pt-4 mt-1 border-top">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add to Tracking') }}
            </button>
            <a href="{{ route('site.monitoring') }}" class="btn btn-outline-secondary">{{ __('To my projects') }}</a>
        </div>

        {!! Form::close() !!}
    </div>

    @slot('js')
        <script src="{{ asset('plugins/site-monitoring/js/site-monitoring.js') }}"></script>
        <script>
            (function () {
                var tgBox = document.getElementById('cabinet-sm-notify-telegram');
                var emailBox = document.getElementById('cabinet-sm-notify-email');
                var hint = document.getElementById('cabinet-sm-notify-off-hint');
                var emailAvailable = @json($siteMonitoringEmailAvailable ?? true);

                function anyNotifyOn() {
                    return (tgBox && tgBox.checked) || (emailBox && emailBox.checked);
                }

                function syncNotifyHint() {
                    if (hint) {
                        hint.classList.toggle('d-none', anyNotifyOn());
                    }
                }

                if (tgBox) {
                    tgBox.addEventListener('change', syncNotifyHint);
                }
                if (emailBox) {
                    emailBox.addEventListener('change', syncNotifyHint);
                    if (!emailAvailable) {
                        emailBox.addEventListener('click', function (e) {
                            e.preventDefault();
                            emailBox.checked = false;
                        });
                    }
                }
                syncNotifyHint();
            })();
        </script>
    @endslot
@endcomponent
