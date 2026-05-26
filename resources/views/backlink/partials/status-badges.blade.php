@php
    $phrases = array_filter(array_map('trim', explode('.', $status ?? '')));
@endphp
@if(count($phrases) === 0)
    <span class="badge text-bg-secondary">{{ __('not checked') }}</span>
@else
    <div class="cabinet-bl-status-badges">
        @foreach($phrases as $phrase)
            @if($phrase === '')
                @continue
            @endif
            <span class="badge {{ strpos($phrase, 'not') === false ? 'text-bg-success' : 'text-bg-danger' }}">
                {{ __($phrase) }}
            </span>
        @endforeach
    </div>
@endif
