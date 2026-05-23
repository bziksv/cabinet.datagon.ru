<div class="cabinet-ta-export-bar d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-secondary small me-1">{{ __('Export and actions') }}:</span>
        <form method="post" action="{{ route('text.analyzer.export.excel') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>{{ __('Download Excel') }}
            </button>
        </form>
        <form method="post" action="{{ route('text.analyzer.export.pdf') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('Download PDF report') }}
            </button>
        </form>
    </div>

    <div class="cabinet-ta-public-share flex-grow-1" id="cabinet-ta-public-share"
         data-create-url="{{ route('text.analyzer.public.share.create') }}"
         data-revoke-url="{{ route('text.analyzer.public.share.revoke') }}">
        <script>
            window.cabinetTaShareLabels = {
                refresh: @json(__('Refresh public link')),
                validUntil: @json(__('Valid until')),
                revokeConfirm: @json(__('Revoke public link') . '?')
            };
        </script>
        <span class="text-secondary small d-block mb-1">{{ __('Public link without registration') }}</span>
        <div class="input-group input-group-sm mb-1" style="max-width: 36rem;">
            <input type="text"
                   class="form-control font-monospace"
                   id="cabinet-ta-public-share-url"
                   readonly
                   placeholder="{{ __('Create a public link to copy it here') }}"
                   value="{{ isset($publicShare) ? $publicShare->publicUrl() : '' }}">
            <button type="button"
                    class="btn btn-outline-secondary"
                    id="cabinet-ta-public-share-copy"
                    @if(empty($publicShare)) disabled @endif
                    title="{{ __('Copy') }}">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button"
                    class="btn btn-primary btn-sm"
                    id="cabinet-ta-public-share-create">
                <i class="bi bi-link-45deg me-1"></i>
                {{ isset($publicShare) ? __('Refresh public link') : __('Create public link') }}
            </button>
            <button type="button"
                    class="btn btn-outline-danger btn-sm"
                    id="cabinet-ta-public-share-revoke"
                    @if(empty($publicShare)) disabled @endif>
                {{ __('Revoke public link') }}
            </button>
            <span class="text-muted small" id="cabinet-ta-public-share-expires">
                @if(!empty($publicShare))
                    {{ __('Valid until') }}: {{ $publicShare->expires_at->format('d.m.Y H:i') }}
                @endif
            </span>
        </div>
    </div>
</div>
