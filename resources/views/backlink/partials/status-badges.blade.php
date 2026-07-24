@php
    $rawStatus = $status ?? '';
    if ($rawStatus === 1 || $rawStatus === '1' || $rawStatus === true) {
        $rawStatus = 'Link found, anchor matches.';
    }
    // Точка или запятая — разные форматы (ручная проверка / старый cron).
    $phrases = preg_split('/[.,]+/u', (string) $rawStatus) ?: [];
    $phrases = array_values(array_filter(array_map('trim', $phrases)));
@endphp
@if(count($phrases) === 0)
    <span class="badge text-bg-secondary">{{ __('not checked') }}</span>
@else
    <div class="cabinet-bl-status-badges">
        @foreach($phrases as $phrase)
            @if($phrase === '')
                @continue
            @endif
            @php
                $low = mb_strtolower($phrase);
                $isHard = \App\Services\Backlink\BacklinkChecker::statusMeansHardBroken($phrase);
                $isWarn = ! $isHard && (
                    mb_strpos($low, 'not placed') !== false
                    || mb_strpos($low, 'not have') !== false
                    || mb_strpos($low, 'не помещена') !== false
                    || mb_strpos($low, 'отсутствует') !== false
                    || mb_strpos($low, 'nofollow is missing') !== false
                );
                $tone = $isHard ? 'text-bg-danger' : ($isWarn ? 'text-bg-warning' : 'text-bg-success');
            @endphp
            <span class="badge {{ $tone }}">
                {{ __($phrase) }}
            </span>
        @endforeach
    </div>
@endif
