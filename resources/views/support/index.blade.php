@extends('support.layout')

@section('support-header')
    {{ __('Support') }}
@endsection

@section('support-above')
    @include('support.partials.stats')
@endsection

@section('support-main')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">
                <i class="bi bi-inbox me-1"></i>
                {{ $isStaff ? __('All tickets') : __('My tickets') }}
            </h3>
            <div class="card-tools">
                <form method="get" action="{{ route('support.index') }}" class="d-flex gap-2">
                    @if(($filter ?? 'all') !== 'all')
                        <input type="hidden" name="status" value="{{ $filter }}">
                    @endif
                    <div class="input-group input-group-sm" style="width: 16rem">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search"
                               name="q"
                               class="form-control"
                               value="{{ $search }}"
                               placeholder="{{ __('Search tickets') }}…"
                               aria-label="{{ __('Search') }}">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Search') }}</button>
                    @if($search !== '')
                        <a href="{{ route('support.index', array_filter(['status' => ($filter ?? 'all') !== 'all' ? $filter : null])) }}"
                           class="btn btn-sm btn-outline-secondary"
                           title="{{ __('Clear search') }}">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>
        <div class="card-body p-0 cabinet-support-list">
            @if($tickets->isEmpty())
                <div class="text-center text-secondary py-5 px-3">
                    <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                    @if($search !== '')
                        <p class="mb-0">{{ __('No tickets match your search.') }}</p>
                        <p class="small mb-3">«{{ $search }}»</p>
                        <a href="{{ route('support.index', array_filter(['status' => ($filter ?? 'all') !== 'all' ? $filter : null])) }}"
                           class="btn btn-outline-secondary btn-sm">{{ __('Clear search') }}</a>
                    @else
                        <p class="mb-0">{{ __('No tickets yet') }}</p>
                        <p class="small mb-3">{{ __('Create a ticket and we will reply here.') }}</p>
                        <a href="{{ route('support.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil-square me-1"></i>{{ __('New ticket') }}
                        </a>
                    @endif
                </div>
            @else
                <div class="d-flex align-items-center px-3 py-2 border-bottom text-secondary small">
                    <span class="ms-auto">{{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} {{ __('of') }} {{ $tickets->total() }}</span>
                </div>
                <ul class="list-group list-group-flush mb-0">
                    @foreach($tickets as $ticket)
                        @php
                            $latest = $ticket->latestMessage;
                            $preview = $latest ? \Illuminate\Support\Str::limit(strip_tags($latest->body), 100) : '';
                            $highlight = !$isStaff && $ticket->hasNewReplyForOwner();
                            $staffHighlight = $isStaff && $ticket->needsStaffAttention();
                            $owner = $ticket->user;
                            $ownerName = $owner->fullName ?: $owner->email;
                        @endphp
                        <li class="list-group-item d-flex align-items-center gap-2 py-2 {{ $highlight || $staffHighlight ? 'cabinet-support-list__item--active' : '' }}">
                            @if($isStaff)
                                @include('support.partials.avatar', [
                                    'src' => $owner->image,
                                    'name' => $ownerName,
                                    'size' => 36,
                                ])
                            @elseif($highlight)
                                <span class="cabinet-support-dot flex-shrink-0" title="{{ __('New reply from support') }}"></span>
                            @else
                                <span class="cabinet-support-dot flex-shrink-0 cabinet-support-dot--muted" aria-hidden="true"></span>
                            @endif
                            <a href="{{ route('support.show', $ticket) }}"
                               class="flex-grow-1 d-flex flex-column flex-md-row gap-md-3 text-decoration-none text-body min-w-0 py-1">
                                @if($isStaff)
                                    <span class="cabinet-support-list__from text-truncate">
                                        <span class="fw-semibold d-block">{{ $ownerName }}</span>
                                        <span class="small text-secondary">{{ $owner->email }}</span>
                                    </span>
                                @endif
                                <span class="flex-grow-1 min-w-0">
                                    <span class="badge {{ $ticket->statusBadgeClass() }} me-2">
                                        {{ $ticket->statusLabel() }}
                                    </span>
                                    <span class="{{ $highlight || $staffHighlight ? 'fw-semibold' : '' }}">
                                        #{{ $ticket->id }} — {{ $ticket->subject }}
                                    </span>
                                    @if($preview)
                                        <span class="d-block fw-normal text-secondary small cabinet-support-preview mt-1">
                                            {{ $preview }}
                                        </span>
                                    @endif
                                </span>
                                <span class="text-secondary small text-md-end text-nowrap flex-shrink-0">
                                    {{ $ticket->updated_at->diffForHumans() }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @if($tickets->hasPages())
            <div class="card-footer py-2">
                {{ $tickets->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
@endsection
