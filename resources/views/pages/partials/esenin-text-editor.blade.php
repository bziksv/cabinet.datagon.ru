<div class="cabinet-esenin-editor" data-esenin-editor>
    <ul class="nav nav-pills nav-fill mb-2 cabinet-esenin-editor-views" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" data-esenin-editor-view="split" aria-selected="true">
                <i class="bi bi-layout-split me-1" aria-hidden="true"></i>{{ __('Esenin text check editor view split') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" data-esenin-editor-view="html" aria-selected="false">
                <i class="bi bi-code-slash me-1" aria-hidden="true"></i>{{ __('Esenin text check editor view html') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" data-esenin-editor-view="plain" aria-selected="false">
                <i class="bi bi-text-paragraph me-1" aria-hidden="true"></i>{{ __('Esenin text check editor view plain') }}
            </button>
        </li>
    </ul>

    <div class="cabinet-esenin-editor-panel" data-esenin-editor-panel="split">
        <div class="cabinet-esenin-split-wrap" data-esenin-split-wrap>
            <div class="cabinet-esenin-split-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <span class="small text-secondary fw-semibold">{{ __('Editor layout') }}</span>
                <div class="btn-group btn-group-sm cabinet-esenin-layout-toggle" role="group" aria-label="{{ __('Editor layout') }}">
                    <button type="button"
                            class="btn btn-outline-secondary active"
                            data-esenin-layout-mode="side"
                            aria-pressed="true">
                        <i class="bi bi-layout-split me-1" aria-hidden="true"></i>{{ __('Side by side') }}
                    </button>
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-esenin-layout-mode="stacked"
                            aria-pressed="false">
                        <i class="bi bi-layout-text-window-reverse me-1" aria-hidden="true"></i>{{ __('Code below editor') }}
                    </button>
                </div>
            </div>

            <div class="cabinet-esenin-split row g-3 cabinet-esenin-split--side"
                 data-esenin-split-editor
                 data-esenin-layout-storage-key="cabinet-esenin-editor-layout">
                <div class="cabinet-esenin-split-col cabinet-esenin-split-col--visual col-12 col-lg-6 d-flex flex-column">
                    <div class="cabinet-esenin-pane flex-grow-1">
                        <div class="cabinet-esenin-pane-head">{{ __('Visual editor') }}</div>
                        <div class="cabinet-esenin-pane-body cabinet-esenin-editor-wrap p-0">
                            <textarea id="cabinet-esenin-text"
                                      class="form-control border-0 rounded-0 cabinet-esenin-textarea"
                                      rows="12"
                                      placeholder="{{ __('Esenin text check text placeholder') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="cabinet-esenin-split-col cabinet-esenin-split-col--code col-12 col-lg-6 d-flex flex-column">
                    <div class="cabinet-esenin-pane flex-grow-1">
                        <div class="cabinet-esenin-pane-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span>{{ __('HTML code') }}</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-esenin-copy-html data-esenin-copied-label="{{ __('HTML copied') }}">
                                <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy HTML') }}
                            </button>
                        </div>
                        <div class="cabinet-esenin-pane-body d-flex flex-column">
                            <div class="cabinet-esenin-code-editor-wrap flex-grow-1" data-esenin-code-wrap>
                                <textarea class="form-control font-monospace cabinet-esenin-html-source"
                                          data-esenin-html-source
                                          rows="12"
                                          spellcheck="false"
                                          aria-label="{{ __('HTML code') }}"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="cabinet-esenin-editor-panel d-none" data-esenin-editor-panel="html">
        <div class="cabinet-esenin-pane">
            <div class="cabinet-esenin-pane-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span>{{ __('HTML code') }}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-esenin-copy-html-full data-esenin-copied-label="{{ __('HTML copied') }}">
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy HTML') }}
                </button>
            </div>
            <div class="cabinet-esenin-pane-body">
                <div class="cabinet-esenin-code-editor-wrap" data-esenin-code-wrap-full>
                    <textarea class="form-control font-monospace cabinet-esenin-html-source"
                              data-esenin-html-source-full
                              rows="16"
                              spellcheck="false"
                              aria-label="{{ __('HTML code') }}"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="cabinet-esenin-editor-panel d-none" data-esenin-editor-panel="plain">
        <label class="form-label fw-semibold" for="cabinet-esenin-plain">{{ __('Esenin text check editor view plain') }}</label>
        <textarea id="cabinet-esenin-plain"
                  class="form-control cabinet-esenin-plain-textarea"
                  rows="16"
                  placeholder="{{ __('Esenin text check text placeholder') }}"></textarea>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 small text-secondary">
        <span data-esenin-char-count
              data-label-text="{{ __('Esenin text check chars text label') }}">0 / {{ number_format($maxChars, 0, ',', ' ') }} {{ __('Esenin text check chars text label') }}</span>
        <span data-esenin-html-meta
              class="text-muted"
              aria-live="polite"
              data-label-html="{{ __('Esenin text check chars html label') }}"></span>
        <span data-esenin-over-limit class="text-danger d-none">{{ __('Esenin text check over limit') }}</span>
    </div>
</div>
