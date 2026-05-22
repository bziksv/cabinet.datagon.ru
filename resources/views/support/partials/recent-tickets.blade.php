@if(isset($recentTickets) && $recentTickets->isNotEmpty())
    <div class="card mt-3 cabinet-support-recent">
        <div class="card-header py-2">
            <h3 class="card-title mb-0 small text-uppercase text-secondary">{{ __('Recent tickets') }}</h3>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush mb-0">
                @foreach($recentTickets as $recent)
                    @php
                        $isActive = isset($activeTicketId) && (int) $activeTicketId === (int) $recent->id;
                        $recentLabel = $recent->subject;
                        if ($isStaff ?? false) {
                            $u = $recent->user;
                            $recentLabel = ($u->fullName ?: $u->email) . ' · ' . \Illuminate\Support\Str::limit($recent->subject, 40);
                        }
                    @endphp
                    <li class="list-group-item p-0">
                        <a href="{{ route('support.show', $recent) }}"
                           class="list-group-item-action py-2 px-3 small d-block {{ $isActive ? 'active' : '' }}">
                            <span class="badge {{ $recent->statusBadgeClass() }} me-1">#{{ $recent->id }}</span>
                            <span class="d-block text-truncate">{{ $recentLabel }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
