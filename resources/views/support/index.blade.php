@component('component.card', ['title' => __('Support')])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-support.css') }}">
    @endslot

    <div class="cabinet-support-page">
        <div class="row g-3">
            @include('support.partials.sidebar')

            <div class="col-lg-9">
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-inbox me-1"></i>
                            {{ $isStaff ? __('All tickets') : __('My tickets') }}
                        </h3>
                        <form method="get" action="{{ route('support.index') }}" class="d-flex gap-2">
                            @if(($filter ?? 'all') !== 'all')
                                <input type="hidden" name="status" value="{{ $filter }}">
                            @endif
                            <div class="input-group input-group-sm" style="min-width: 14rem">
                                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                                <input type="search"
                                       name="q"
                                       class="form-control"
                                       value="{{ $search }}"
                                       placeholder="{{ __('Search') }}…"
                                       aria-label="{{ __('Search') }}">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Search') }}</button>
                        </form>
                    </div>
                    <div class="card-body p-0 cabinet-support-list">
                        @if($tickets->isEmpty())
                            <div class="text-center text-secondary py-5 px-3">
                                <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                                {{ __('No tickets yet') }}
                                <div class="mt-3">
                                    <a href="{{ route('support.create') }}" class="btn btn-primary btn-sm">
                                        {{ __('New ticket') }}
                                    </a>
                                </div>
                            </div>
                        @else
                            <ul class="list-group list-group-flush mb-0">
                                @foreach($tickets as $ticket)
                                    @php
                                        $latest = $ticket->latestMessage;
                                        $preview = $latest ? \Illuminate\Support\Str::limit(strip_tags($latest->body), 120) : '';
                                        $unreadStyle = !$isStaff && $ticket->status === \App\SupportTicket::STATUS_ANSWERED;
                                    @endphp
                                    <li class="list-group-item d-flex align-items-center gap-2 {{ $unreadStyle ? 'fw-semibold bg-body-secondary' : '' }}">
                                        <a href="{{ route('support.show', $ticket) }}"
                                           class="flex-grow-1 d-flex flex-column flex-md-row gap-md-3 text-decoration-none text-body py-1">
                                            @if($isStaff)
                                                <span class="text-truncate" style="min-width: 8rem">
                                                    {{ $ticket->user->fullName ?: $ticket->user->email }}
                                                </span>
                                            @endif
                                            <span class="flex-grow-1 text-truncate">
                                                <span class="badge {{ $ticket->statusBadgeClass() }} me-2">
                                                    {{ $ticket->statusLabel() }}
                                                </span>
                                                #{{ $ticket->id }} — {{ $ticket->subject }}
                                                @if($preview)
                                                    <span class="fw-normal text-secondary cabinet-support-preview">
                                                        — {{ $preview }}
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="text-secondary small text-md-end text-nowrap" style="min-width: 5rem">
                                                {{ $ticket->updated_at->diffForHumans() }}
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @if($tickets->hasPages())
                        <div class="card-footer">
                            {{ $tickets->links('pagination::bootstrap-4') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endcomponent
