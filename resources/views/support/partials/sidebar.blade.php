@php
    use App\SupportTicket;
    $statusFilters = [
        'all' => __('All tickets'),
        SupportTicket::STATUS_OPEN => ($isStaff ?? false) ? __('Needs reply') : __('Awaiting reply'),
        SupportTicket::STATUS_ANSWERED => ($isStaff ?? false) ? __('Answered by support') : __('Answered'),
        SupportTicket::STATUS_CLOSED => __('Closed'),
    ];
    $onCreate = request()->routeIs('support.create');
@endphp

<div class="col-lg-3 cabinet-support-sidebar">
    <a href="{{ route('support.create') }}"
       class="btn btn-primary w-100 mb-3 {{ $onCreate ? 'disabled' : '' }}"
       @if($onCreate) aria-disabled="true" @endif>
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>{{ __('New ticket') }}
    </a>
    <div class="card">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">{{ __('Folders') }}</h3>
        </div>
        <div class="card-body p-0">
            <ul class="nav nav-pills flex-column mb-0">
                @foreach($statusFilters as $code => $label)
                    @php
                        $folderActive = !$onCreate && request()->routeIs('support.index') && ($filter ?? 'all') === $code;
                        $openCount = $counts[SupportTicket::STATUS_OPEN] ?? 0;
                        $badgeClass = ($code === SupportTicket::STATUS_OPEN && ($isStaff ?? false) && $openCount > 0)
                            ? 'text-bg-danger'
                            : (($folderActive) ? 'text-bg-light' : 'text-bg-secondary');
                    @endphp
                    <li class="nav-item">
                        <a href="{{ route('support.index', array_filter(['status' => $code === 'all' ? null : $code, 'q' => $search ?? null])) }}"
                           class="nav-link rounded-0 d-flex justify-content-between {{ $folderActive ? 'active' : '' }}">
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
                                <span class="badge {{ $badgeClass }}">
                                    {{ $counts[$code] }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @include('support.partials.recent-tickets')

    <div class="card mt-3 cabinet-ideas-sidebar-promo border-0 shadow-sm">
        <div class="card-body py-3">
            <p class="small fw-semibold text-body mb-1">
                <i class="bi bi-lightbulb text-warning me-1"></i>{{ __('Ideas board') }}
            </p>
            <p class="small text-secondary mb-2">{{ __('Suggest improvements and vote for the best ideas from other users.') }}</p>
            <a href="{{ route('ideas.index') }}" class="btn btn-sm btn-outline-warning w-100">
                {{ __('Suggest an idea / Vote') }}
            </a>
        </div>
    </div>

    @if($isStaff ?? false)
        <div class="card mt-3">
            <div class="card-body small text-secondary py-3">
                <i class="bi bi-info-circle me-1"></i>
                {{ __('Only administrators can reply to tickets.') }}
            </div>
        </div>
    @else
        <div class="card mt-3">
            <div class="card-body small text-secondary py-3">
                <p class="mb-2 fw-semibold text-body">{{ __('How it works') }}</p>
                <ol class="mb-0 ps-3">
                    <li>{{ __('Create a ticket with your question.') }}</li>
                    <li>{{ __('Support will reply in the same thread.') }}</li>
                    <li>{{ __('You can add details until the ticket is closed.') }}</li>
                </ol>
            </div>
        </div>
    @endif
</div>
