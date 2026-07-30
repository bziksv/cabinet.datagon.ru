@php
    $rawStatus = $status ?? '';
    if ($rawStatus === 1 || $rawStatus === '1' || $rawStatus === true) {
        $rawStatus = 'Link found, anchor matches.';
    } elseif ($rawStatus === 0 || $rawStatus === '0' || $rawStatus === false) {
        $rawStatus = '';
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
                // Контроль=да: ждём ОТСУТСТВИЕ nofollow/noindex. Наличие атрибута/тега — предупреждение.
                $isWarn = ! $isHard && (
                    (mb_strpos($low, 'placed in noindex') !== false && mb_strpos($low, 'not placed') === false)
                    || (mb_strpos($low, 'помещена в noindex') !== false && mb_strpos($low, 'не помещена') === false)
                    || (mb_strpos($low, 'have attribute nofollow') !== false && mb_strpos($low, 'not have') === false)
                    || (mb_strpos($low, 'имеет атрибут nofollow') !== false && mb_strpos($low, 'не имеет') === false)
                );
                $tone = $isHard ? 'text-bg-danger' : ($isWarn ? 'text-bg-warning' : 'text-bg-success');
            @endphp
            <span class="badge {{ $tone }}">
                {{ __($phrase) }}
            </span>
        @endforeach
    </div>
@endif
