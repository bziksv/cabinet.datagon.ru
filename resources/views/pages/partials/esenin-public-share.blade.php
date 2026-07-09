<div class="cabinet-esenin-share rounded border bg-white p-3 d-none"
     data-esenin-public-share
     data-feature-available="{{ $publicShareAvailable ? '1' : '0' }}"
     data-create-url="{{ route('pages.esenin-text-check.public.share.create') }}"
     data-revoke-url="{{ route('pages.esenin-text-check.public.share.revoke') }}">
    <div class="alert alert-warning py-2 px-2 small mb-2 d-none" data-esenin-share-unavailable role="alert">
        {{ __('Esenin text check public share unavailable') }}
    </div>
    <div class="small fw-semibold mb-2">
        <i class="bi bi-share me-1" aria-hidden="true"></i>{{ __('Public link without registration') }}
    </div>
    <div class="input-group input-group-sm mb-2">
        <input type="text"
               class="form-control font-monospace"
               data-esenin-share-url
               readonly
               placeholder="{{ __('Create a public link to copy it here') }}">
        <button type="button" class="btn btn-primary" data-esenin-share-copy disabled>
            <i class="bi bi-clipboard" aria-hidden="true"></i>
        </button>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <label class="visually-hidden" for="cabinet-esenin-share-ttl">{{ __('Site monitoring share ttl label') }}</label>
        <select class="form-select form-select-sm cabinet-esenin-share__ttl"
                id="cabinet-esenin-share-ttl"
                data-esenin-share-ttl
                aria-label="{{ __('Site monitoring share ttl label') }}">
            @foreach($shareTtlOptions as $days => $label)
                <option value="{{ $days }}" @if((int) $days === 30) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-primary btn-sm" data-esenin-share-create>
            <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ __('Create public link') }}
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-esenin-share-revoke disabled>
            {{ __('Revoke public link') }}
        </button>
        <span class="badge rounded-pill text-bg-secondary d-none" data-esenin-share-expires></span>
    </div>
    <p class="small text-secondary mb-0 mt-2">{{ __('Esenin text check public share hint') }}</p>
</div>
