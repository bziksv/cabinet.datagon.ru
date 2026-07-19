@php $general = $general ?? []; @endphp
<div class="col-6 col-lg-4 col-xl-2 d-flex min-w-0">
    <div class="info-box mb-0 flex-fill h-100">
        <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-fonts"></i></span>
        <div class="info-box-content">
            <span class="info-box-text text-wrap">{{ __('Number of words') }}</span>
            <span class="info-box-number">{{ number_format($general['countWordsAll'] ?? $general['countWords'] ?? 0, 0, ',', ' ') }}</span>
        </div>
    </div>
</div>
<div class="col-6 col-lg-4 col-xl-2 d-flex min-w-0">
    <div class="info-box mb-0 flex-fill h-100">
        <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-textarea-t"></i></span>
        <div class="info-box-content">
            <span class="info-box-text text-wrap">{{ __('Number of characters without spaces') }}</span>
            <span class="info-box-number">{{ number_format($general['lengthWithOutSpaces'] ?? 0, 0, ',', ' ') }}</span>
        </div>
    </div>
</div>
<div class="col-6 col-lg-4 col-xl-2 d-flex min-w-0">
    <div class="info-box mb-0 flex-fill h-100">
        <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-type"></i></span>
        <div class="info-box-content">
            <span class="info-box-text text-wrap">{{ __('Number of characters') }}</span>
            <span class="info-box-number">{{ number_format($general['textLength'] ?? 0, 0, ',', ' ') }}</span>
        </div>
    </div>
</div>
<div class="col-6 col-lg-4 col-xl-2 d-flex min-w-0">
    <div class="info-box mb-0 flex-fill h-100">
        <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-funnel"></i></span>
        <div class="info-box-content">
            <span class="info-box-text text-wrap">{{ __('Number of stop words') }}</span>
            <span class="info-box-number">{{ number_format($general['countStopWords'] ?? 0, 0, ',', ' ') }}</span>
        </div>
    </div>
</div>
<div class="col-6 col-lg-4 col-xl-2 d-flex min-w-0">
    <div class="info-box mb-0 flex-fill h-100">
        <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-check2-all"></i></span>
        <div class="info-box-content">
            <span class="info-box-text text-wrap">{{ __('Number of words without stop words') }}</span>
            <span class="info-box-number">{{ number_format($general['countWordsWithoutStopWords'] ?? $general['countWords'] ?? 0, 0, ',', ' ') }}</span>
        </div>
    </div>
</div>
