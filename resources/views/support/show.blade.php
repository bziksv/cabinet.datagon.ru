@php
    $owner = $ticket->user;
    $ownerName = $owner->fullName ?: $owner->email;
@endphp
@extends('support.layout')

@section('support-header')
    {{ __('Ticket') }} #{{ $ticket->id }}
@endsection

@section('support-header-tools')
    <div class="btn-group btn-group-sm">
        @if(!empty($ticketNav['prev']))
            <a href="{{ route('support.show', $ticketNav['prev']) }}"
               class="btn btn-outline-secondary"
               title="{{ __('Newer ticket') }}">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </a>
        @else
            <span class="btn btn-outline-secondary disabled"><i class="bi bi-chevron-up"></i></span>
        @endif
        @if(!empty($ticketNav['next']))
            <a href="{{ route('support.show', $ticketNav['next']) }}"
               class="btn btn-outline-secondary"
               title="{{ __('Older ticket') }}">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </a>
        @else
            <span class="btn btn-outline-secondary disabled"><i class="bi bi-chevron-down"></i></span>
        @endif
    </div>
    <a href="{{ route('support.index', array_filter(['status' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null])) }}"
       class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to list') }}
    </a>
@endsection

@section('support-main')
    <div class="card cabinet-support-thread">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div class="min-w-0">
                <h3 class="card-title mb-1 text-break">{{ $ticket->subject }}</h3>
                <div class="small text-secondary">
                    @if($isStaff)
                        <span class="me-2">
                            <i class="bi bi-person me-1"></i>{{ $ownerName }}
                        </span>
                        <span class="me-2">{{ $owner->email }}</span>
                    @endif
                    <span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                    <span class="ms-2">{{ __('Created') }} {{ $ticket->created_at->format('d.m.Y H:i') }}</span>
                    <span class="ms-2">{{ __('Updated') }} {{ $ticket->updated_at->diffForHumans() }}</span>
                </div>
            </div>
            @if($canReopen ?? false)
                {!! Form::open(['route' => ['support.reopen', $ticket], 'method' => 'PATCH', 'class' => 'd-inline flex-shrink-0']) !!}
                <button type="submit" class="btn btn-sm btn-primary"
                        onclick='return confirm(@json(__('Reopen this ticket?')))'>
                    <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reopen ticket') }}
                </button>
                {!! Form::close() !!}
            @elseif(!$ticket->isClosed())
                {!! Form::open(['route' => ['support.close', $ticket], 'method' => 'PATCH', 'class' => 'd-inline flex-shrink-0']) !!}
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        onclick='return confirm(@json(__('Close this ticket?')))'>
                    <i class="bi bi-archive me-1"></i>{{ __('Close ticket') }}
                </button>
                {!! Form::close() !!}
            @endif
        </div>
        <div class="card-body cabinet-support-messages" id="cabinet-support-messages">
            @foreach($ticket->messages as $message)
                @php
                    $author = $message->user;
                    $authorName = $author->fullName ?: __('User');
                @endphp
                <div class="d-flex gap-3 align-items-start mb-4 cabinet-support-message">
                    @include('support.partials.avatar', [
                        'src' => $author->image,
                        'name' => $authorName,
                        'size' => 48,
                    ])
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                            <div>
                                <span class="fw-semibold">{{ $authorName }}</span>
                                @if($message->is_staff)
                                    <span class="badge text-bg-primary ms-1">{{ __('Support') }}</span>
                                @endif
                            </div>
                            <small class="text-secondary text-nowrap">
                                {{ $message->created_at->format('d.m.Y H:i') }}
                                <span class="d-none d-md-inline"> · {{ $message->created_at->diffForHumans() }}</span>
                            </small>
                        </div>
                        <div class="cabinet-support-bubble {{ $message->is_staff ? 'cabinet-support-bubble--staff' : 'cabinet-support-bubble--user' }}">
                            {!! nl2br(e($message->body)) !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if($canStaffReply)
            @include('support.partials.reply-form', ['ticket' => $ticket, 'asStaff' => true])
        @elseif($canUserReply)
            @include('support.partials.reply-form', ['ticket' => $ticket, 'asStaff' => false])
        @elseif($ticket->isClosed())
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-secondary small mb-0">
                    <i class="bi bi-lock me-1"></i>{{ __('This ticket is closed.') }}
                    @if($ticket->closed_at)
                        <span class="ms-1">{{ __('Closed at') }} {{ $ticket->closed_at->format('d.m.Y H:i') }}</span>
                    @endif
                </span>
                @if($canReopen ?? false)
                    {!! Form::open(['route' => ['support.reopen', $ticket], 'method' => 'PATCH', 'class' => 'mb-0']) !!}
                    <button type="submit" class="btn btn-sm btn-primary"
                            onclick='return confirm(@json(__('Reopen this ticket?')))'>
                        <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reopen ticket') }}
                    </button>
                    {!! Form::close() !!}
                @endif
            </div>
        @elseif($isStaff)
            <div class="card-footer text-secondary small">
                <i class="bi bi-check-circle me-1"></i>{{ __('Waiting for the user to reply or close the ticket.') }}
            </div>
        @else
            <div class="card-footer text-secondary small">
                <i class="bi bi-hourglass-split me-1"></i>{{ __('Waiting for a response from support.') }}
            </div>
        @endif
    </div>
@endsection

