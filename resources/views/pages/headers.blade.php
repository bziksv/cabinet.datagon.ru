@php
    $hasResponse = !empty($response) && is_array($response) && count($response) > 0;
    $singleUrl = request('url');
    $publicShareUrl = ($hasResponse && !empty($id))
        ? url('/public/http-headers/' . $id . '?lang=' . urlencode($lang ?? 'ru'))
        : '';
@endphp

@component('component.card', [
    'title' => __('Http headers'),
    'titleHtml' => e(__('Http headers')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-http-headers'])->render(),
])
    @slot('css')
        @include('pages.partials.http-headers-styles')
    @endslot

    <div class="cabinet-hh-page">
        @include('pages.partials.http-headers-module-nav', ['active' => 'check'])

        <div class="cabinet-hh-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-hh-lead__icon" aria-hidden="true">
                    <i class="bi bi-hdd-network"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Http headers lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Http headers lead hint') }}</p>
                </div>
            </div>
        </div>

        @auth
            <section class="cabinet-hh-panel card border shadow-sm" aria-labelledby="cabinet-hh-single-title">
                <div class="card-body">
                    <h2 class="cabinet-hh-step-title h6 mb-3" id="cabinet-hh-single-title">
                        <span class="cabinet-hh-step-badge text-bg-secondary">·</span>
                        <span>{{ __('Http headers single check title') }}</span>
                    </h2>
                    {!! Form::open(['method' => 'GET', 'route' => 'pages.headers', 'class' => 'cabinet-hh-single-form']) !!}
                    <label class="form-label fw-medium" for="cabinet-hh-single-url">{{ __('To check one link') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
                        {!! Form::text('url', $singleUrl, [
                            'id' => 'cabinet-hh-single-url',
                            'class' => 'form-control' . ($errors->has('url') ? ' is-invalid' : ''),
                            'placeholder' => 'https://example.com/',
                        ]) !!}
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('Check URL') }}
                        </button>
                    </div>
                    <div class="form-text">{{ __('Http headers single check hint') }}</div>
                    {!! Form::close() !!}
                </div>
            </section>
        @endauth

        <response-http-code
            submit="{{ __('Send') }}"
            url-title="{{ __('url') }}"
            code-title="{{ __('code') }}"
            text-title="{{ __('Bulk check up to 500 pieces at a time') }}"
            timeout-title="{{ __('Timeout between requests in ms') }}"
            export-btn="{{ __('Export') }}"
            open-new-page="{{ __('Open in a new window') }}"
            more="{{ __('More') }}"
            bulk-step-title="{{ __('Http headers bulk step title') }}"
            bulk-hint="{{ __('Http headers bulk hint') }}"
            urls-placeholder="{{ __('Http headers urls placeholder') }}"
            results-title="{{ __('Http headers results title') }}"
            clear-btn="{{ __('Clear') }}"
            progress-label="{{ __('Http headers progress label') }}"
            kpi-total="{{ __('Http headers kpi total') }}"
            kpi-ok="{{ __('Http headers kpi ok') }}"
            kpi-error="{{ __('Http headers kpi error') }}"
            status-title="{{ __('Status') }}"
            status-ok="{{ __('Available') }}"
            status-fail="{{ __('Unavailable') }}"
        ></response-http-code>

        @if($hasResponse)
            @if($publicShareUrl !== '')
            <section class="cabinet-hh-share cabinet-hh-panel card border shadow-sm" aria-labelledby="cabinet-hh-share-title">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2" id="cabinet-hh-share-title">
                        <i class="bi bi-share me-1 text-primary" aria-hidden="true"></i>{{ __('Copy link') }}
                    </h2>
                    <div class="input-group input-group-sm">
                        <input type="text"
                               id="cabinet-hh-share-input"
                               class="form-control font-monospace"
                               value="{{ $publicShareUrl }}"
                               readonly>
                        <button type="button" class="input-group-text" id="cabinet-hh-share-copy" title="{{ __('Copy link') }}">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="form-text mb-0 mt-2">{{ __('Http headers public share hint') }}</p>
                </div>
            </section>
            @endif

            <div id="response-code">
                @foreach($response as $arItems)
                    @php
                        $isOk = (int) ($arItems['status'] ?? 0) === 200;
                    @endphp
                    <section class="cabinet-hh-result-card card border shadow-sm mb-0 @if($isOk) border-success @else border-danger @endif">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3 @if($isOk) text-bg-success-subtle @else text-bg-danger-subtle @endif">
                            <h3 class="h6 mb-0 fw-semibold">
                                <i class="bi bi-reply me-1" aria-hidden="true"></i>{{ __('HTTP Code') }}:
                                <span class="badge rounded-pill @if($isOk) text-bg-success @else text-bg-danger @endif ms-1">{{ $arItems['status'] }}</span>
                            </h3>
                        </div>
                        <div class="card-body p-0 overflow-auto">
                            <table class="table table-striped table-hover mb-0">
                                <tbody>
                                @foreach($arItems['headers'] as $name => $val)
                                    <tr>
                                        <td>{{ $name }}</td>
                                        <td class="text-break">@if(is_array($val)){{ implode(', ', $val) }}@else{{ $val }}@endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>

            @php $lastItem = last($response); @endphp
            @if(!empty($lastItem['content']))
                <section class="cabinet-hh-html-card card border shadow-sm" aria-labelledby="cabinet-hh-html-title">
                    <div class="card-header py-3">
                        <h3 class="h6 mb-0 fw-semibold" id="cabinet-hh-html-title">
                            <i class="bi bi-code-slash me-1 text-primary" aria-hidden="true"></i>{{ __('HTML Code') }}
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <textarea id="cabinet-hh-html-code" class="d-none">{{ $lastItem['content'] }}</textarea>
                    </div>
                </section>
            @endif
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/jquery-ui/jquery-ui.js') }}"></script>
        <script src="{{ asset('plugins/codemirror/codemirror.js') }}"></script>
        <script src="{{ asset('plugins/codemirror/mode/css/css.js') }}"></script>
        <script src="{{ asset('plugins/codemirror/mode/xml/xml.js') }}"></script>
        <script src="{{ asset('plugins/codemirror/mode/htmlmixed/htmlmixed.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/jszip/jszip.js') }}"></script>
        <script src="{{ asset('plugins/pdfmake/pdfmake.min.js') }}"></script>
        <script src="{{ asset('plugins/pdfmake/vfs_fonts.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.print.js') }}"></script>

        <script>
            (function () {
                var codeEl = document.getElementById('cabinet-hh-html-code');
                if (codeEl && window.CodeMirror) {
                    var editor = CodeMirror.fromTextArea(codeEl, {
                        mode: 'htmlmixed',
                        lineNumbers: true,
                        readOnly: true,
                    });
                    if (typeof $ !== 'undefined' && $.fn.resizable) {
                        $(editor.getWrapperElement()).resizable({ handles: 's' });
                    }
                }

                var copyBtn = document.getElementById('cabinet-hh-share-copy');
                var copyInput = document.getElementById('cabinet-hh-share-input');
                if (copyBtn && copyInput) {
                    copyBtn.addEventListener('click', function () {
                        var value = copyInput.value;
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(value).then(showCopied).catch(fallbackCopy);
                        } else {
                            fallbackCopy();
                        }
                        function fallbackCopy() {
                            copyInput.select();
                            copyInput.setSelectionRange(0, 99999);
                            document.execCommand('copy');
                            showCopied();
                        }
                        function showCopied() {
                            if (typeof $ !== 'undefined' && $(document).Toasts) {
                                $(document).Toasts('create', {
                                    class: 'bg-success',
                                    title: @json(__('Copied link')),
                                    autohide: true,
                                    delay: 2000,
                                });
                            }
                        }
                    });
                }
            })();
        </script>
    @endslot
@endcomponent
