@php
    use App\SupportTicket;
    $statusFilters = [
        'all' => __('All tickets'),
        SupportTicket::STATUS_OPEN => __('Awaiting reply'),
        SupportTicket::STATUS_ANSWERED => __('Answered'),
        SupportTicket::STATUS_CLOSED => __('Closed'),
    ];
@endphp

<div class="col-lg-3">
    <a href="{{ route('support.create') }}" class="btn btn-primary w-100 mb-3">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>{{ __('New ticket') }}
    </a>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">{{ __('Folders') }}</h3>
        </div>
        <div class="card-body p-0">
            <ul class="nav nav-pills flex-column mb-0">
                @foreach($statusFilters as $code => $label)
                    <li class="nav-item">
                        <a href="{{ route('support.index', array_filter(['status' => $code === 'all' ? null : $code, 'q' => $search ?? null])) }}"
                           class="nav-link rounded-0 d-flex justify-content-between {{ ($filter ?? 'all') === $code ? 'active' : '' }}">
                            <span>
                                @if($code === 'all')
                                    <i class="bi bi-inbox me-2" aria-hidden="true"></i>
                                @elseif($code === SupportTicket::STATUS_OPEN)
                                    <i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>
                                @elseif($code === SupportTicket::STATUS_ANSWERED)
                                    <i class="bi bi-check-circle me-2" aria-hidden="true"></i>
                                @else
                                    <i class="bi bi-archive me-2" aria-hidden="true"></i>
                                @endif
                                {{ $label }}
                            </span>
                            @if(($counts[$code] ?? 0) > 0)
                                <span class="badge {{ ($filter ?? 'all') === $code ? 'text-bg-light' : 'text-bg-secondary' }}">
                                    {{ $counts[$code] }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    @if($isStaff ?? false)
        <div class="card mt-3">
            <div class="card-body small text-secondary">
                <i class="bi bi-info-circle me-1"></i>
                {{ __('Only administrators can reply to tickets.') }}
            </div>
        </div>
    @endif
</div>
