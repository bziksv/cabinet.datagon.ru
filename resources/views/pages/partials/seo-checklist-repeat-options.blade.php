@php
    $selected = $selected ?? '';
    $labels = \App\Support\SeoChecklistDefaultTemplate::repeatRuleLabels();
    if ($selected === 'weekly' && !isset($labels['weekly'])) {
        $labels = ['weekly' => __('Every 7 days')] + $labels;
    }
@endphp
<option value="">{{ __('No repeat') }}</option>
@foreach($labels as $value => $label)
    <option value="{{ $value }}" @if($selected === $value) selected @endif>{{ $label }}</option>
@endforeach
