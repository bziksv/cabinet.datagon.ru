@component('component.card', [
    'title' => __('Password generator'),
    'titleHtml' => e(__('Password generator')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-password-generator'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-password-generator.css') }}?v={{ @filemtime(public_path('css/cabinet-password-generator.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.PasswordGenerator {
                background: oldlace;
            }
        </style>
    @endslot

    @php
        $savedPasswords = $user->passwords ?? collect();
        $savedCount = $savedPasswords->count();
    @endphp

    <div class="cabinet-pw-toasts" aria-live="polite">
        <div class="toast-top-right success-message" hidden>
            <div class="toast toast-success">
                <div class="toast-message">{{ __('Success') }}</div>
            </div>
        </div>
        <div class="toast-top-right error-message" hidden>
            <div class="toast toast-error" aria-live="assertive">
                <div class="toast-message">{{ __('Error') }}</div>
            </div>
        </div>
    </div>

    <div class="cabinet-pw-page">
        <p class="cabinet-pw-lead">{{ __('Password generator lead') }}</p>

        <div class="row g-3 cabinet-pw-kpi">
            <div class="col-6 col-md-4 col-lg-3">
                <div class="info-box shadow-sm mb-0">
                    <span class="info-box-icon text-bg-primary">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Password generator saved count') }}</span>
                        <span class="info-box-number">{{ number_format($savedCount, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-8 col-lg-9 d-flex align-items-center">
                <p class="small text-secondary mb-0">{{ __('Password generator saved hint') }}</p>
            </div>
        </div>

        <div class="row g-3 align-items-stretch">
            <div class="col-lg-7">
                <div class="card shadow-sm cabinet-pw-panel h-100">
                    <div class="card-header">
                        <h2 class="card-title">{{ __('Generator settings') }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="cabinet-pw-presets mb-3">
                            <span class="text-muted small me-2">{{ __('Presets') }}:</span>
                            <div class="btn-group btn-group-sm flex-wrap">
                                <button type="button" class="btn btn-outline-secondary" data-pw-preset="strong">
                                    {{ __('Password generator preset strong') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-pw-preset="letters">
                                    {{ __('Password generator preset letters') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-pw-preset="pin">
                                    {{ __('Password generator preset pin') }}
                                </button>
                            </div>
                        </div>

                        <form id="cabinet-pw-form" action="{{ route('generate.password') }}" method="post">
                            @csrf
                            <div class="cabinet-pw-options">
                                <div class="form-check">
                                    <input type="checkbox" id="checkbox1" class="form-check-input cabinet-pw-option click_tracking" data-click="Enums" name="enums" checked>
                                    <label for="checkbox1" class="form-check-label">{{ __('Enums') }}</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" id="checkbox2" class="form-check-input cabinet-pw-option click_tracking" data-click="Upper case" name="upperCase" checked>
                                    <label for="checkbox2" class="form-check-label">{{ __('Upper case') }}</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" id="checkbox3" class="form-check-input cabinet-pw-option click_tracking" data-click="Lower case" name="lowerCase" checked>
                                    <label for="checkbox3" class="form-check-label">{{ __('Lower case') }}</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" id="checkbox4" class="form-check-input cabinet-pw-option click_tracking" data-click="Special symbols" name="specialSymbols" checked>
                                    <label for="checkbox4" class="form-check-label">
                                        {{ __('Special symbols') }}
                                        <span class="cabinet-pw-symbols-hint">%, *, ), ?, @, #, $, ~</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" id="checkbox5" class="form-check-input cabinet-pw-option" name="savePassword">
                                    <label for="checkbox5" class="form-check-label">{{ __('Save password') }}</label>
                                </div>
                            </div>

                            <div class="cabinet-pw-length mt-3">
                                <label class="form-label mb-1" for="cabinet-pw-length">
                                    {{ __('Characters') }}:
                                    <span class="cabinet-pw-length-value" data-pw-length-value>15</span>
                                </label>
                                <input type="range" class="form-range cabinet-pw-option" id="cabinet-pw-length-range" min="1" max="50" value="15" aria-hidden="true" tabindex="-1">
                                <input type="number" class="form-control form-control-sm cabinet-pw-option" id="cabinet-pw-length" name="countSymbols" value="15" max="50" min="1" required>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4 click_tracking" data-click="Generate password">
                                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('Generate password') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm cabinet-pw-results-card h-100">
                    <div class="card-header">
                        <h2 class="card-title">{{ __('Generated passwords') }}</h2>
                    </div>
                    <div class="card-body d-flex flex-column">
                        @if(!empty($passwords))
                            <ul class="cabinet-pw-results-list">
                                @foreach($passwords as $password)
                                    <li>
                                        <code>{{ $password }}</code>
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                data-pw-copy="{{ $password }}"
                                                data-pw-copy-msg="{{ __('Successfully copied') }}"
                                                title="{{ __('Copy to Clipboard') }}">
                                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                                            <span class="visually-hidden">{{ __('Copy to Clipboard') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="small text-secondary mt-3 mb-0">{{ __('Password generator results hint') }}</p>
                        @else
                            <div class="cabinet-pw-empty flex-grow-1 d-flex flex-column justify-content-center">
                                <i class="bi bi-key" aria-hidden="true"></i>
                                <p class="mb-0">{{ __('Password generator results empty') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm cabinet-pw-saved-card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="card-title mb-0">{{ __('Your generated passwords') }}</h2>
                @if($savedCount > 0)
                    <span class="badge text-bg-light border">{{ $savedCount }}</span>
                @endif
            </div>
            <div class="card-body p-0">
                <p class="cabinet-pw-comment-hint small text-secondary mb-0 px-3 py-2 border-bottom bg-body-tertiary">
                    <i class="bi bi-lightbulb me-1" aria-hidden="true"></i>{{ __('Password generator comment hint') }}
                </p>
                @if($savedCount > 0)
                    <div class="table-responsive">
                        <table id="me-passwords-table" class="table table-sm table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Password') }}</th>
                                <th scope="col">{{ __('Comment') }}</th>
                                <th scope="col" class="text-nowrap">{{ __('Created at') }}</th>
                                <th scope="col" class="text-end">{{ __('Actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($savedPasswords as $password)
                                <tr id="tr-{{ $password->id }}">
                                    <td class="cabinet-pw-password-cell align-middle">{{ $password->password }}</td>
                                    <td class="align-middle">
                                        <textarea class="form-control password-comment"
                                                  name="comment"
                                                  id="{{ $password->id }}"
                                                  rows="2"
                                                  placeholder="{{ __('Password generator comment placeholder') }}"
                                                  data-comment-url="{{ route('edit.password.comment') }}"
                                                  data-comment-success="{{ __('Comment successfully changed') }}"
                                                  data-comment-error="{{ __('Error') }}">{{ $password->comment }}</textarea>
                                    </td>
                                    <td class="align-middle text-nowrap small text-secondary">
                                        {{ $password->created_at ? $password->created_at->format('d.m.Y H:i') : '—' }}
                                    </td>
                                    <td class="align-middle">
                                        <div class="cabinet-pw-actions">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    data-pw-copy="{{ $password->password }}"
                                                    data-pw-copy-msg="{{ __('Successfully copied') }}"
                                                    title="{{ __('Copy to Clipboard') }}">
                                                <i class="bi bi-clipboard" aria-hidden="true"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm remove-password click_tracking"
                                                    data-click="Remove"
                                                    data-order="{{ $password->id }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#removePasswordWindow"
                                                    title="{{ __('Remove') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="cabinet-pw-saved-empty">
                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50" aria-hidden="true"></i>
                        <p class="mb-0">{{ __('Password generator saved empty') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="removePasswordWindow" tabindex="-1" aria-labelledby="removePasswordWindowLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removePasswordWindowLabel">{{ __('Are you sure?') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-secondary">{{ __('Password generator delete confirm') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button"
                            data-bs-dismiss="modal"
                            id="success-remove-password"
                            class="btn btn-danger"
                            data-remove-url="{{ route('remove.password') }}"
                            data-remove-success="{{ __('Successfully deleted') }}">
                        {{ __('Remove') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="passwordId" id="passwordId">

    @slot('js')
        <script src="{{ asset('js/cabinet-password-generator.js') }}?v={{ @filemtime(public_path('js/cabinet-password-generator.js')) ?: time() }}"></script>
    @endslot
@endcomponent
