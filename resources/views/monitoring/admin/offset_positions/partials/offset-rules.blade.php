@php
    $defaultRules = [
        ['from' => 11, 'to' => 16, 'operator' => '+', 'count' => 6],
        ['from' => 17, 'to' => 22, 'operator' => '+', 'count' => 12],
        ['from' => '', 'to' => '', 'operator' => '-', 'count' => ''],
    ];
@endphp
<div id="mon-offset-rules" class="cabinet-mon-offset-rules">
    @foreach($defaultRules as $index => $rule)
        <div class="cabinet-mon-offset-rule mb-3" data-rule-index="{{ $index }}">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <h4 class="h6 mb-0">{{ __('Monitoring offset rule title', ['num' => $index + 1]) }}</h4>
                @if($index < 2)
                    <span class="badge text-bg-light border">{{ __('Monitoring offset rule preset') }}</span>
                @endif
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-6 col-sm-3">
                    <label class="form-label small mb-1" for="mon-offset-from-{{ $index }}">{{ __('Monitoring offset field from') }}</label>
                    <input type="number"
                           min="1"
                           class="form-control form-control-sm"
                           id="mon-offset-from-{{ $index }}"
                           name="offset[{{ $index }}][from]"
                           value="{{ $rule['from'] }}"
                           placeholder="11">
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label small mb-1" for="mon-offset-to-{{ $index }}">{{ __('Monitoring offset field to') }}</label>
                    <input type="number"
                           min="1"
                           class="form-control form-control-sm"
                           id="mon-offset-to-{{ $index }}"
                           name="offset[{{ $index }}][to]"
                           value="{{ $rule['to'] }}"
                           placeholder="16">
                </div>
                <div class="col-6 col-sm-2">
                    <label class="form-label small mb-1" for="mon-offset-op-{{ $index }}">{{ __('Monitoring offset field operator') }}</label>
                    <select class="form-select form-select-sm"
                            id="mon-offset-op-{{ $index }}"
                            name="offset[{{ $index }}][operator]">
                        <option value="-" @if($rule['operator'] === '-') selected @endif>−</option>
                        <option value="+" @if($rule['operator'] === '+') selected @endif>+</option>
                    </select>
                </div>
                <div class="col-6 col-sm-4">
                    <label class="form-label small mb-1" for="mon-offset-count-{{ $index }}">{{ __('Monitoring offset field count') }}</label>
                    <input type="number"
                           min="1"
                           class="form-control form-control-sm"
                           id="mon-offset-count-{{ $index }}"
                           name="offset[{{ $index }}][count]"
                           value="{{ $rule['count'] }}"
                           placeholder="6">
                </div>
            </div>
            <p class="form-text small mb-0 mt-1">{{ __('Monitoring offset rule hint') }}</p>
        </div>
    @endforeach
</div>
