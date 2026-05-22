@component('component.card', ['title' => __('Balance')])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-balance.css') }}">
    @endslot

    @php
        $user = Auth::user();
        $balanceFormatted = number_format((float) $user->balance, 0, '.', ' ');
    @endphp

    <div class="cabinet-balance-page">
        <div class="row g-3 mb-4 align-items-stretch cabinet-balance-stats">
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm">
                        <i class="bi bi-wallet2"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Your balance') }}</span>
                        <span class="info-box-number">{{ $balanceFormatted }} ₽</span>
                        <span class="info-box-meta invisible" aria-hidden="true">&nbsp;</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm">
                        <i class="bi bi-arrow-down-circle"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Top-ups') }}</span>
                        <span class="info-box-number">{{ $topUpsCount }}</span>
                        <span class="info-box-meta invisible" aria-hidden="true">&nbsp;</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-secondary shadow-sm">
                        <i class="bi bi-clock-history"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Last operation') }}</span>
                        <span class="info-box-number">
                            @if($lastTopUp)
                                +{{ number_format((float) $lastTopUp->sum, 0, '.', ' ') }} ₽
                            @else
                                —
                            @endif
                        </span>
                        <span class="info-box-meta text-secondary">
                            @if($lastTopUp)
                                {{ $lastTopUp->created_at->diffForHumans() }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-success mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-1"></i>{{ __('Top up your balance') }}
                </h3>
            </div>
            {!! Form::open(['method' => 'POST', 'route' => ['balance-add.store']]) !!}
            <div class="card-body">
                <p class="text-secondary small mb-3">
                    {{ __('Funds are credited after successful payment. You can choose a tariff after topping up.') }}
                    <a href="{{ route('tariff.index') }}" class="text-nowrap">{{ __('Tariffs') }}</a>
                </p>

                <div class="row g-3 align-items-end">
                    <div class="col-md-5 col-lg-4">
                        {!! Form::label('sum', __('Sum'), ['class' => 'form-label']) !!}
                        <div class="input-group input-group-lg">
                            {!! Form::number('sum', old('sum'), [
                                'class' => 'form-control' . ($errors->has('sum') ? ' is-invalid' : ''),
                                'min' => '1',
                                'step' => '1',
                                'id' => 'balance-sum',
                                'placeholder' => '1000',
                            ]) !!}
                            <span class="input-group-text">₽</span>
                        </div>
                        @error('sum')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-7 col-lg-5">
                        <span class="form-label d-block">{{ __('Quick amount') }}</span>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach([500, 1000, 3000, 5000, 10000] as $preset)
                                <button type="button"
                                        class="btn btn-outline-secondary cabinet-balance-preset"
                                        data-sum="{{ $preset }}">
                                    {{ number_format($preset, 0, '.', ' ') }} ₽
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        {!! Form::submit(__('Replenish'), ['class' => 'btn btn-success btn-lg w-100']) !!}
                    </div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                    <h3 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-list-ul"></i>
                        <span>{{ __('History') }}</span>
                        <span class="badge rounded-pill text-bg-secondary fw-normal">{{ $balances->total() }}</span>
                    </h3>
                    @if($balances->hasPages())
                        <span class="text-secondary small mb-0">
                            {{ __('Showing') }}
                            <strong>{{ $balances->firstItem() }}–{{ $balances->lastItem() }}</strong>
                            {{ __('of') }}
                            <strong>{{ $balances->total() }}</strong>
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Sum') }}</th>
                            <th scope="col">{{ __('Source') }}</th>
                            <th scope="col" class="text-end text-nowrap">{{ __('Date') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($balances as $balance)
                            <tr>
                                <td>
                                    @switch($balance->status)
                                        @case(0)
                                            <span class="badge text-bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>{{ __($balance->statuses[$balance->status]) }}
                                            </span>
                                            @break
                                        @case(1)
                                            <span class="badge text-bg-success">
                                                <i class="bi bi-plus-circle me-1"></i>{{ __($balance->statuses[$balance->status]) }}
                                            </span>
                                            @break
                                        @case(2)
                                            <span class="badge text-bg-info">
                                                <i class="bi bi-dash-circle me-1"></i>{{ __($balance->statuses[$balance->status]) }}
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end fw-semibold text-nowrap">
                                    @if($balance->status === 2)
                                        −{{ number_format((float) $balance->sum, 0, '.', ' ') }}
                                    @else
                                        {{ number_format((float) $balance->sum, 0, '.', ' ') }}
                                    @endif
                                    ₽
                                </td>
                                <td class="text-secondary">{{ __($balance->source) }}</td>
                                <td class="text-end text-secondary text-nowrap">
                                    <time datetime="{{ $balance->created_at->toIso8601String() }}">
                                        {{ $balance->created_at->diffForHumans() }}
                                    </time>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="cabinet-balance-empty text-center text-secondary">
                                    <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                                    {{ __('No records') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($balances->hasPages())
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-secondary small mb-0">
                        {{ __('Showing') }}
                        <strong>{{ $balances->firstItem() }}–{{ $balances->lastItem() }}</strong>
                        {{ __('of') }}
                        <strong>{{ $balances->total() }}</strong>
                    </span>
                    {{ $balances->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>

    @if($response == 'success')
        @include('balance.success')
    @endif

    @if($response == 'fail')
        @include('balance.fail')
    @endif

    @slot('js')
        <script>
            (function () {
                document.querySelectorAll('.cabinet-balance-preset').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var input = document.getElementById('balance-sum');
                        if (input) {
                            input.value = btn.getAttribute('data-sum');
                            input.focus();
                        }
                    });
                });
            })();
        </script>

        @if($response)
            <script>
                (function () {
                    var url = new URL(window.location.href);
                    var invId = url.searchParams.get('InvId');
                    if (invId === null) {
                        var block = document.getElementById('counting-metrics-block');
                        if (block) {
                            block.remove();
                        }
                        return;
                    }
                    $.ajax({
                        type: 'post',
                        dataType: 'json',
                        url: "{{ route('counting.metrics') }}",
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: invId,
                        },
                        success: function (response) {
                            if (response.click) {
                                $('.modal').modal('show');
                                $('#counting-metrics').trigger('click');
                            }
                            $('#counting-metrics-block').remove();
                        },
                    });
                })();
            </script>
        @else
            <script>
                var block = document.getElementById('counting-metrics-block');
                if (block) {
                    block.remove();
                }
            </script>
        @endif
    @endslot
@endcomponent
