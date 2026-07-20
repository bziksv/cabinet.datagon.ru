@component('component.card', [
    'title' => __('Keyword generator'),
    'titleHtml' => e(__('Keyword generator')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-keyword-generator'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}" />
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/keyword-generator/css/style.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/cabinet-keyword-generator.css') }}?v={{ @filemtime(public_path('css/cabinet-keyword-generator.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.GeneratorWords {
                background: oldlace;
            }
        </style>
    @endslot

    <div class="cabinet-kw-page">
        <p class="cabinet-kw-lead">{{ __('Keyword generator lead') }}</p>

        <div id="keyword-generator">
            <section class="cabinet-kw-step" aria-labelledby="cabinet-kw-step-lists">
                <h2 class="cabinet-kw-step__title" id="cabinet-kw-step-lists">
                    <span class="cabinet-kw-step__badge" aria-hidden="true">1</span>
                    {{ __('Keyword generator step lists') }}
                </h2>
                <p class="small text-secondary mb-2">{{ __('Keyword generator step lists hint') }}</p>
                <div class="cabinet-kw-lists-panel">
                    <div class="listContainer">
                        <div class="addList" role="button" tabindex="0">
                            <div class="generatorAddPluse" aria-hidden="true">+</div>
                            <div class="generatorAddText">{{ __('Add') }}<br>{{ __('word list') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="popup popup-result-content" hidden aria-hidden="true">
                <div class="cabinet-kw-result-template">
                    <div class="cabinet-kw-result-filter mb-3">
                        <label class="form-label small mb-1" for="cabinet-kw-filter-template">{{ __('Leave phrases containing') }}</label>
                        <input id="cabinet-kw-filter-template" class="filter_word form-control form-control-sm" type="text" autocomplete="off" />
                    </div>
                    <p class="cabinet-kw-result-count small text-secondary mb-2">
                        <span class="generatedCountText">{{ __('Phrases received') }}:</span>
                        <strong class="generatedCount text-primary"></strong>
                    </p>
                    <textarea title="" rows="14" class="result_word_generator form-control font-monospace" readonly></textarea>
                    <div class="cabinet-kw-result-actions d-flex flex-wrap justify-content-end gap-2 mt-3">
                        <button type="button" class="save-result-word-generator btn btn-outline-secondary btn-sm click_tracking" data-click="Save">
                            <i class="bi bi-download" aria-hidden="true"></i>
                            {{ __('Save') }}
                        </button>
                        <button type="button" class="copy-result-word-generator btn btn-primary btn-sm click_tracking" data-click="Copy">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                            {{ __('Copy') }}
                        </button>
                    </div>
                </div>
            </div>

            <section class="cabinet-kw-step" aria-labelledby="cabinet-kw-step-settings">
                <h2 class="cabinet-kw-step__title" id="cabinet-kw-step-settings">
                    <span class="cabinet-kw-step__badge" aria-hidden="true">2</span>
                    {{ __('Additional settings') }}
                </h2>
                <div class="cabinet-kw-settings-panel">
                    <div class="optionsHeader click_tracking" data-click="Additional settings">
                        <a href="#" class="arrow arrowDown additionalGlobalOptions __dashed">{{ __('Show keyword generator options') }}</a>
                    </div>
                    <div class="globalOptions">
                        <div class="globalOptionsList">
                            <div class="cabinet-kw-option-row">
                                <label class="ui_label __no-select cabinet-kw-option-label">
                                    <span class="ui_checkbox">
                                        <input type="checkbox" class="globalCheckboxOption ui_checkbox_input click_tracking" data-click="Conclude in quotation marks" value="surroundWithQuotes"/>
                                        <span class="ui_checkbox_fake-input"></span>
                                    </span>
                                    <span class="cabinet-kw-option-text">{{ __('Conclude in') }} &quot; &quot;</span>
                                    @include('pages.partials.keyword-generator-tip', [
                                        'wide' => true,
                                        'content' => __('Phrase match operator.') . ' ' . __('Works in Yandex.Direct and Google AdWords differently.'),
                                    ])
                                </label>
                            </div>

                            <div class="cabinet-kw-option-row">
                                <label class="ui_label __no-select cabinet-kw-option-label">
                                    <span class="ui_checkbox">
                                        <input type="checkbox" class="globalCheckboxOption ui_checkbox_input click_tracking" data-click="Conclude in staples"
                                               value="surroundWithBrackets"/>
                                        <span class="ui_checkbox_fake-input"></span>
                                    </span>
                                    <span class="cabinet-kw-option-text">{{ __('Conclude in') }}&nbsp;&laquo;[ ]&raquo;</span>
                                    @include('pages.partials.keyword-generator-tip', [
                                        'wide' => true,
                                        'content' => __('In Yandex.Direct, it fixes the order of words, taking into account word forms and stop words. In Google AdWords, restricts impressions to a keyword and its related variants.'),
                                    ])
                                </label>
                            </div>

                            <div class="cabinet-kw-option-row">
                                <label class="ui_label __no-select cabinet-kw-option-label">
                                    <span class="ui_checkbox">
                                        <input type="checkbox" class="globalCheckboxOption ui_checkbox_input click_tracking" data-click="Add combinations without operators" value="addToResult"/>
                                        <span class="ui_checkbox_fake-input"></span>
                                    </span>
                                    <span class="cabinet-kw-option-text">{{ __('Add combinations without operators') }}</span>
                                    @include('pages.partials.keyword-generator-tip', [
                                        'content' => __('Variants without operators are added to combinations with "" or [].'),
                                    ])
                                </label>
                            </div>

                            <div class="cabinet-kw-option-row">
                                <label class="ui_label __no-select cabinet-kw-option-label">
                                    <span class="ui_checkbox">
                                        <input type="checkbox" class="globalCheckboxOption ui_checkbox_input click_tracking" data-click="Add to stop words" value="addPlus"/>
                                        <span class="ui_checkbox_fake-input"></span>
                                    </span>
                                    <span class="cabinet-kw-option-text">{{ __('Add "+" to stop words') }}</span>
                                    @include('pages.partials.keyword-generator-tip', [
                                        'content' => __('Allows you to take into account stop words in Yandex.Direct.'),
                                    ])
                                </label>
                            </div>

                            <div class="cabinet-kw-option-row cabinet-kw-option-row--split">
                                <div class="cabinet-kw-split-line">
                                    <label class="ui_label __no-select cabinet-kw-option-label mb-0">
                                        <span class="ui_checkbox">
                                            <input type="checkbox" class="globalCheckboxOption ui_checkbox_input click_tracking"
                                                   data-click="Split into phrases"
                                                   value="getAllPhrasesByLength"/>
                                            <span class="ui_checkbox_fake-input"></span>
                                        </span>
                                        <span class="cabinet-kw-option-text">{{ __('Split into phrases from') }}</span>
                                    </label>
                                    <select class="from-words form-select form-select-sm cabinet-kw-split-num" aria-label="{{ __('Split into phrases from') }}">
                                        <option value="1">1</option>
                                        <option value="2" selected>2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                    </select>
                                    <span class="cabinet-kw-split-sep">{{ __('before') }}</span>
                                    <select class="to-words form-select form-select-sm cabinet-kw-split-num" aria-label="{{ __('before') }}">
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7" selected>7</option>
                                    </select>
                                    <span class="cabinet-kw-split-sep">{{ __('Words Crop') }}</span>
                                    <select class="left-right form-select form-select-sm cabinet-kw-split-side" aria-label="{{ __('Words Crop') }}">
                                        <option value="right" selected>{{ __('right') }}</option>
                                        <option value="left">{{ __('left') }}</option>
                                        <option value="both">{{ __('at both sides') }}</option>
                                    </select>
                                    @include('pages.partials.keyword-generator-tip', [
                                        'wide' => true,
                                        'content' => __('Each phrase from the combined is divided into smaller ones with the specified number of words. Extra words are cut off.'),
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cabinet-kw-step" aria-labelledby="cabinet-kw-step-run">
                <h2 class="cabinet-kw-step__title" id="cabinet-kw-step-run">
                    <span class="cabinet-kw-step__badge" aria-hidden="true">3</span>
                    {{ __('Get combinations') }}
                </h2>
                <div class="cabinet-kw-run-row">
                    <button type="button" class="get btn btn-primary click_tracking" data-click="Get combinations">
                        <i class="bi bi-magic" aria-hidden="true"></i>
                        {{ __('Get combinations') }}
                    </button>
                    <p class="small text-secondary mb-0">
                        {{ __('You get combinations') }}:
                        <strong class="combinationsQuantity"></strong>
                        @include('pages.partials.keyword-generator-tip', [
                            'wide' => true,
                            'content' => __('If you select Split into phrases, the number of combinations may change.'),
                        ])
                    </p>
                </div>
            </section>
        </div>
    </div>

    <div class="words-localized">
        <input type="hidden" id="Additionally" value="{{__('Additionally')}}">
        <input type="hidden" id="Combine-without" value="{{__('Combine without these words')}}">
        <input type="hidden" id="Variants-without-words" value="{{__('Variants without words from this list are added to combinations from other lists.')}}">
        <input type="hidden" id="Phrases-are-being-generated" value="{{__('Phrases are being generated')}}">
        <input type="hidden" id="This-may-take-some-time" value="{{__('This may take some time.')}}">
        <input type="hidden" id="Words" value="{{__('Words')}}">
        <input type="hidden" id="Leave-phrases" value="{{__('Leave phrases containing')}}">
        <input type="hidden" id="Result-popup-title" value="{{ __('Keyword generator result title') }}">
        <input type="hidden" id="Words-from-this-list" value="{{__('Words from this list will be added, including without combining with others.')}}">
        <input type="hidden" id="Add-source-list" value="{{__('Add source list')}}">
        <input type="hidden" id="Add" value="{{__('Add')}}">
        <input type="hidden" id="Broad-match" value="{{__('Broad match modifier for Google AdWords. A "+" is added before each word.')}}">
        <input type="hidden" id="Use-to-set" value="{{__('Use to set impressions in the specified word form in Yandex.Direct. Before each word a "!" Is added, before the stop words "+".')}}">
        <input type="hidden" id="word-list" value="{{__('Word list')}}">
        <input type="hidden" id="word-placeholder" placeholder="{{ __('Enter or paste keywords, one per line. Blank lines are ignored. To combine all lists except the selected one, click Add combinations without these words') }}" value="">
    </div>

    @slot('js')
        <script type="text/javascript" src="{{ asset('plugins/keyword-generator/js/require.js') }}"></script>
        <script type="text/javascript" src="{{ asset('plugins/keyword-generator/js/require-config.js') }}"></script>

        <script>
            require(['keywordGenerator/word_generator', 'jquery'], function (WordGenerator, $) {
                WordGenerator.keywordGeneratorStart(
                    $('#keyword-generator'),
                    "{{ asset('plugins/keyword-generator/js/apps/keywordGenerator/') }}"
                );
                @php $demoKw = \App\Support\DemoCabinet::isCurrentUser() ? \App\Support\DemoCabinet::keywordGeneratorShowcase() : null; @endphp
                @if($demoKw)
                (function applyDemoKeyword() {
                    var demo = @json($demoKw);
                    var tries = 0;
                    var timer = setInterval(function () {
                        tries += 1;
                        var $areas = $('#keyword-generator .wordList');
                        if ($areas.length < 2) {
                            if (tries > 40) {
                                clearInterval(timer);
                            }
                            return;
                        }
                        clearInterval(timer);
                        (demo.lists || []).forEach(function (text, index) {
                            if ($areas[index]) {
                                $($areas[index]).val(String(text)).trigger('input');
                            }
                        });
                        setTimeout(function () {
                            $('#keyword-generator .get').first().trigger('click');
                        }, 200);
                    }, 100);
                })();
                @endif
            });
        </script>
    @endslot
@endcomponent
