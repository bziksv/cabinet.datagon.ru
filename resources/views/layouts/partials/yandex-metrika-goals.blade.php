{{-- Цели Метрики по факту успеха (не по клику кнопки). --}}
@if(config('app.env') !== 'local')
    @php
        $ymCounter = 89500732;
        $ymRegistered = session()->pull('ym_registered');
        $ymVerified = session()->pull('ym_verified') || session()->pull('verified');
    @endphp
    @if($ymRegistered || $ymVerified)
        <script type="text/javascript">
            (function () {
                var counter = {{ $ymCounter }};
                function reach(goal) {
                    var tries = 0;
                    function fire() {
                        if (typeof ym === 'function') {
                            ym(counter, 'reachGoal', goal);
                            return true;
                        }
                        return false;
                    }
                    if (fire()) {
                        return;
                    }
                    var timer = setInterval(function () {
                        if (fire() || ++tries > 40) {
                            clearInterval(timer);
                        }
                    }, 50);
                }
                @if($ymRegistered)
                reach('novaja_registracija_1231');
                @endif
                @if($ymVerified)
                reach('verifikacija_po_majlu_1628');
                if (window._tmr && typeof window._tmr.push === 'function') {
                    window._tmr.push({ type: 'reachGoal', id: 3340935, goal: 'Verifikacija170523' });
                }
                @endif
            })();
        </script>
    @endif
@endif
