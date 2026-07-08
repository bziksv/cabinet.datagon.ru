<div class="form-group mb-3">
    <label class="form-label fw-semibold" for="cabinet-mon-create-google-depth">{{ __('Monitoring google depth label') }}</label>
    <select id="cabinet-mon-create-google-depth" class="form-select">
        @foreach(\App\Classes\Monitoring\MonitoringGoogleDepth::options() as $depth)
            <option value="{{ $depth }}" @if($depth === 10) selected @endif>{{ __('Monitoring google depth top', ['n' => $depth]) }}</option>
        @endforeach
    </select>
    <p class="text-secondary small mb-0 mt-1">{{ __('Monitoring google depth hint create') }}</p>
</div>
<div class="form-group">
    <label>Начните вводить город — на русском или английском (от 2 букв).</label>
    <select name="lr[google][]" data-search="google" class="Select2 cabinet-mon-create-region-select" multiple="multiple" style="width: 100%;"></select>
</div>
<!-- /.form-group -->
